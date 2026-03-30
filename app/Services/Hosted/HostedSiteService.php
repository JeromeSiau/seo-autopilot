<?php

namespace App\Services\Hosted;

use App\Models\Article;
use App\Models\HostedDeployEvent;
use App\Models\HostedAuthor;
use App\Models\HostedAsset;
use App\Models\HostedCategory;
use App\Models\HostedExportRun;
use App\Models\HostedNavigationItem;
use App\Models\HostedPage;
use App\Models\HostedRedirect;
use App\Models\HostedTag;
use App\Models\Integration;
use App\Models\Site;
use App\Models\SiteHosting;
use App\Models\Team;
use App\Services\Hosting\PloiTenantService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class HostedSiteService
{
    public function __construct(
        private readonly HostedPageGenerator $pageGenerator,
        private readonly HostedContentSanitizer $sanitizer,
        private readonly PloiTenantService $ploi,
    ) {}

    public function createHostedSite(Team $team, array $attributes): Site
    {
        return DB::transaction(function () use ($team, $attributes) {
            /** @var Site $site */
            $site = $team->sites()->create([
                ...$attributes,
                'mode' => Site::MODE_HOSTED,
                'crawl_status' => 'completed',
                'crawl_pages_count' => 0,
            ]);

            $site->hosting()->create([
                'template_key' => SiteHosting::TEMPLATE_EDITORIAL,
                'theme_settings' => $this->pageGenerator->defaultTheme($site),
                'domain_status' => SiteHosting::DOMAIN_STATUS_NONE,
                'ssl_status' => SiteHosting::SSL_STATUS_NONE,
            ]);

            $this->ensureHostedIntegration($site);
            $this->pageGenerator->ensureDefaults($site);
            $this->syncStaticPages($site->fresh(['hosting', 'hostedPages']));

            return $site->fresh(['hosting', 'hostedPages']);
        });
    }

    public function ensureHostedIntegration(Site $site): Integration
    {
        return Integration::updateOrCreate(
            ['site_id' => $site->id],
            [
                'team_id' => $site->team_id,
                'type' => 'hosted',
                'name' => "{$site->name} hosted blog",
                'credentials' => [],
                'is_active' => true,
            ]
        );
    }

    public function provisionStaging(Site $site): SiteHosting
    {
        $this->assertHosted($site);

        $hosting = $site->hosting ?: $site->hosting()->create([
            'template_key' => SiteHosting::TEMPLATE_EDITORIAL,
            'theme_settings' => $this->pageGenerator->defaultTheme($site),
        ]);

        if ($hosting->staging_domain) {
            return $hosting;
        }

        $stagingDomain = $this->generateStagingDomain($site);

        try {
            $this->ploi->createTenant($stagingDomain);
            $this->ploi->requestCertificate($stagingDomain, [$stagingDomain], $this->webhookUrl());
        } catch (Throwable $exception) {
            $hosting->update([
                'domain_status' => SiteHosting::DOMAIN_STATUS_ERROR,
                'ssl_status' => SiteHosting::SSL_STATUS_ERROR,
                'last_error' => $exception->getMessage(),
            ]);

            $this->recordDeployEvent(
                $site,
                'staging_provision_failed',
                HostedDeployEvent::STATUS_ERROR,
                'Staging provisioning failed',
                $exception->getMessage(),
                ['staging_domain' => $stagingDomain]
            );

            throw $exception;
        }

        $hosting->update([
            'staging_domain' => $stagingDomain,
            'canonical_domain' => $stagingDomain,
            'domain_status' => SiteHosting::DOMAIN_STATUS_ACTIVE,
            'ssl_status' => SiteHosting::SSL_STATUS_PENDING,
            'staging_certificate_requested_at' => now(),
        ]);

        $this->recordDeployEvent(
            $site,
            'staging_provisioned',
            HostedDeployEvent::STATUS_SUCCESS,
            'Staging domain provisioned',
            "Provisioned {$stagingDomain} and requested its certificate.",
            ['staging_domain' => $stagingDomain]
        );

        $site->refresh()->load('hosting');
        $this->syncPublishedUrls($site);
        $this->syncStaticPages($site);

        return $site->hosting;
    }

    public function updateCustomDomain(Site $site, string $domain): SiteHosting
    {
        $this->assertHosted($site);

        $domain = $this->normalizeDomain($domain);

        $hosting = $site->hosting ?: $site->hosting()->create([
            'template_key' => SiteHosting::TEMPLATE_EDITORIAL,
            'theme_settings' => $this->pageGenerator->defaultTheme($site),
        ]);

        $hosting->update([
            'custom_domain' => $domain,
            'domain_status' => SiteHosting::DOMAIN_STATUS_DNS_PENDING,
            'ssl_status' => SiteHosting::SSL_STATUS_NONE,
            'last_error' => null,
        ]);

        $this->recordDeployEvent(
            $site,
            'custom_domain_saved',
            HostedDeployEvent::STATUS_INFO,
            'Custom domain saved',
            "Saved {$domain}. DNS verification is now required.",
            ['custom_domain' => $domain]
        );

        return $hosting->fresh();
    }

    public function verifyCustomDomain(Site $site): array
    {
        $this->assertHosted($site);
        $hosting = $site->hosting()->firstOrFail();

        if (!$hosting->custom_domain) {
            throw new RuntimeException('No custom domain configured.');
        }

        $verification = $this->checkDns($hosting->custom_domain);

        if (!$verification['matched']) {
            $hosting->update([
                'domain_status' => SiteHosting::DOMAIN_STATUS_DNS_PENDING,
                'last_error' => 'DNS records do not match the expected target.',
            ]);

            $this->recordDeployEvent(
                $site,
                'dns_verification_pending',
                HostedDeployEvent::STATUS_WARNING,
                'DNS verification pending',
                'DNS records do not match the expected target yet.',
                ['verification' => $verification]
            );

            return $verification;
        }

        try {
            $this->ploi->createTenant($hosting->custom_domain);
            $this->ploi->requestCertificate(
                $hosting->custom_domain,
                [$hosting->custom_domain],
                $this->webhookUrl()
            );
        } catch (Throwable $exception) {
            $hosting->update([
                'domain_status' => SiteHosting::DOMAIN_STATUS_ERROR,
                'ssl_status' => SiteHosting::SSL_STATUS_ERROR,
                'last_error' => $exception->getMessage(),
            ]);

            $this->recordDeployEvent(
                $site,
                'certificate_request_failed',
                HostedDeployEvent::STATUS_ERROR,
                'Certificate request failed',
                $exception->getMessage(),
                ['custom_domain' => $hosting->custom_domain]
            );

            throw $exception;
        }

        $hosting->update([
            'domain_status' => SiteHosting::DOMAIN_STATUS_SSL_PENDING,
            'ssl_status' => SiteHosting::SSL_STATUS_PENDING,
            'custom_domain_verified_at' => now(),
            'custom_certificate_requested_at' => now(),
            'last_error' => null,
        ]);

        $this->recordDeployEvent(
            $site,
            'certificate_requested',
            HostedDeployEvent::STATUS_SUCCESS,
            'SSL request started',
            "DNS verified for {$hosting->custom_domain}. Certificate request started.",
            ['verification' => $verification, 'custom_domain' => $hosting->custom_domain]
        );

        return $verification + ['matched' => true];
    }

    public function handleCertificateWebhook(string $domain, array $payload = []): ?SiteHosting
    {
        $domain = $this->normalizeDomain($domain);

        /** @var SiteHosting|null $hosting */
        $hosting = SiteHosting::query()
            ->where('staging_domain', $domain)
            ->orWhere('custom_domain', $domain)
            ->first();

        if (!$hosting) {
            Log::warning('Ploi webhook received for unknown domain', ['domain' => $domain, 'payload' => $payload]);
            return null;
        }

        $isCustom = $hosting->custom_domain === $domain;

        $hosting->update([
            'ssl_status' => SiteHosting::SSL_STATUS_ACTIVE,
            'domain_status' => $isCustom ? SiteHosting::DOMAIN_STATUS_ACTIVE : $hosting->domain_status,
            'canonical_domain' => $isCustom ? $hosting->custom_domain : ($hosting->canonical_domain ?: $hosting->staging_domain),
            'last_error' => null,
        ]);

        $site = $hosting->site()->first();

        if ($site) {
            $this->syncPublishedUrls($site->fresh(['hosting']));
            $this->syncStaticPages($site->fresh(['hosting', 'hostedPages']));

            $this->recordDeployEvent(
                $site,
                'certificate_issued',
                HostedDeployEvent::STATUS_SUCCESS,
                'Certificate webhook received',
                "SSL is active for {$domain}.",
                ['domain' => $domain, 'payload' => $payload]
            );
        }

        return $hosting->fresh();
    }

    public function queueSiteExport(Site $site): HostedExportRun
    {
        $this->assertHosted($site);

        $hosting = $site->hosting()->firstOrFail();
        $targetPath = $this->hostedSiteExportPath($site);

        /** @var HostedExportRun $run */
        $run = $hosting->exportRuns()->create([
            'status' => HostedExportRun::STATUS_PENDING,
            'target_path' => $targetPath,
            'metadata' => [
                'site_id' => $site->id,
            ],
        ]);

        $this->recordDeployEvent(
            $site,
            'export_requested',
            HostedDeployEvent::STATUS_INFO,
            'ZIP export queued',
            'A new hosted export was queued for generation.',
            ['run_id' => $run->id, 'target_path' => $targetPath]
        );

        return $run->fresh();
    }

    public function startSiteExportRun(Site $site, HostedExportRun $run, string $targetPath): HostedExportRun
    {
        $this->assertHosted($site);

        $run->update([
            'status' => HostedExportRun::STATUS_RUNNING,
            'target_path' => $targetPath,
            'error_message' => null,
            'started_at' => now(),
            'completed_at' => null,
        ]);

        $this->recordDeployEvent(
            $site,
            'export_started',
            HostedDeployEvent::STATUS_INFO,
            'ZIP export started',
            'Hosted export generation is running.',
            ['run_id' => $run->id, 'target_path' => $targetPath]
        );

        return $run->fresh();
    }

    public function completeSiteExportRun(Site $site, HostedExportRun $run, string $targetPath): HostedExportRun
    {
        $this->assertHosted($site);

        clearstatcache(true, $targetPath);

        $run->update([
            'status' => HostedExportRun::STATUS_COMPLETED,
            'target_path' => $targetPath,
            'size_bytes' => is_file($targetPath) ? filesize($targetPath) ?: null : null,
            'completed_at' => now(),
        ]);

        $this->recordDeployEvent(
            $site,
            'export_completed',
            HostedDeployEvent::STATUS_SUCCESS,
            'ZIP export completed',
            'Hosted export is ready to download.',
            [
                'run_id' => $run->id,
                'target_path' => $targetPath,
                'size_bytes' => $run->fresh()->size_bytes,
            ]
        );

        return $run->fresh();
    }

    public function failSiteExportRun(Site $site, HostedExportRun $run, Throwable|string $error): HostedExportRun
    {
        $this->assertHosted($site);

        $message = $error instanceof Throwable ? $error->getMessage() : (string) $error;

        $run->update([
            'status' => HostedExportRun::STATUS_FAILED,
            'error_message' => $message,
            'completed_at' => now(),
        ]);

        $site->hosting?->update([
            'last_error' => $message,
        ]);

        $this->recordDeployEvent(
            $site,
            'export_failed',
            HostedDeployEvent::STATUS_ERROR,
            'ZIP export failed',
            $message,
            ['run_id' => $run->id, 'target_path' => $run->target_path]
        );

        return $run->fresh();
    }

    public function recordDeployEvent(
        Site $site,
        string $type,
        string $status,
        string $title,
        ?string $message = null,
        array $metadata = []
    ): ?HostedDeployEvent {
        $this->assertHosted($site);

        $hosting = $site->hosting()->first();

        if (!$hosting) {
            return null;
        }

        /** @var HostedDeployEvent $event */
        $event = $hosting->deployEvents()->create([
            'type' => $type,
            'status' => $status,
            'title' => $title,
            'message' => $message,
            'metadata' => $metadata,
            'occurred_at' => now(),
        ]);

        return $event->fresh();
    }

    public function updateTheme(Site $site, array $themeSettings, ?string $templateKey = null): SiteHosting
    {
        $this->assertHosted($site);
        $hosting = $site->hosting()->firstOrFail();
        $defaults = $this->pageGenerator->defaultTheme($site);

        foreach (['logo_asset_id', 'social_image_asset_id'] as $assetKey) {
            if (array_key_exists($assetKey, $themeSettings) && filled($themeSettings[$assetKey])) {
                $asset = $site->hostedAssets()
                    ->where('id', $themeSettings[$assetKey])
                    ->where('is_active', true)
                    ->firstOrFail();

                $themeSettings[$assetKey] = $asset->id;
            } elseif (array_key_exists($assetKey, $themeSettings) && blank($themeSettings[$assetKey])) {
                $themeSettings[$assetKey] = null;
            }
        }

        $hosting->update([
            'template_key' => $templateKey ?: $hosting->template_key,
            'theme_settings' => array_replace_recursive($defaults, $hosting->theme_settings ?? [], $themeSettings),
        ]);

        return $hosting->fresh();
    }

    public function updatePage(Site $site, string $kind, array $data): HostedPage
    {
        $this->assertHosted($site);

        if (!in_array($kind, HostedPage::SYSTEM_KINDS, true)) {
            throw new RuntimeException('Unsupported hosted page kind.');
        }

        /** @var HostedPage $page */
        $page = $site->hostedPages()->firstOrNew(['kind' => $kind]);
        $defaults = $this->systemPageDefaults($kind);

        $page->fill([
            'slug' => $defaults['slug'],
            'title' => $data['title'],
            'navigation_label' => filled($data['navigation_label'] ?? null)
                ? $data['navigation_label']
                : ($page->navigation_label ?: $data['title']),
            'body_html' => $this->sanitizer->sanitize($data['body_html'] ?? ''),
            'sections' => $this->normalizeHostedSections($data['sections'] ?? []),
            'meta_title' => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
            'canonical_url' => $data['canonical_url'] ?? null,
            'social_title' => $data['social_title'] ?? null,
            'social_description' => $data['social_description'] ?? null,
            'social_image_asset_id' => $data['social_image_asset_id'] ?? null,
            'social_image_url' => $data['social_image_url'] ?? null,
            'robots_noindex' => (bool) ($data['robots_noindex'] ?? false),
            'schema_enabled' => (bool) ($data['schema_enabled'] ?? true),
            'show_in_sitemap' => (bool) ($data['show_in_sitemap'] ?? ($defaults['show_in_sitemap'] ?? true)),
            'show_in_feed' => (bool) ($data['show_in_feed'] ?? false),
            'breadcrumbs_enabled' => (bool) ($data['breadcrumbs_enabled'] ?? true),
            'show_in_navigation' => (bool) ($data['show_in_navigation'] ?? $defaults['show_in_navigation']),
            'sort_order' => (int) ($data['sort_order'] ?? $defaults['sort_order']),
            'is_published' => (bool) ($data['is_published'] ?? true),
        ]);
        $page->save();

        $this->syncStaticPages($site->fresh(['hosting', 'hostedPages']));

        return $page;
    }

    public function createCustomPage(Site $site, array $data): HostedPage
    {
        $this->assertHosted($site);

        /** @var HostedPage $page */
        $page = $site->hostedPages()->create([
            'kind' => HostedPage::KIND_CUSTOM,
            'slug' => $data['slug'],
            'title' => $data['title'],
            'navigation_label' => filled($data['navigation_label'] ?? null) ? $data['navigation_label'] : $data['title'],
            'body_html' => $this->sanitizer->sanitize($data['body_html'] ?? ''),
            'sections' => $this->normalizeHostedSections($data['sections'] ?? []),
            'meta_title' => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
            'canonical_url' => $data['canonical_url'] ?? null,
            'social_title' => $data['social_title'] ?? null,
            'social_description' => $data['social_description'] ?? null,
            'social_image_asset_id' => $data['social_image_asset_id'] ?? null,
            'social_image_url' => $data['social_image_url'] ?? null,
            'robots_noindex' => (bool) ($data['robots_noindex'] ?? false),
            'schema_enabled' => (bool) ($data['schema_enabled'] ?? true),
            'show_in_sitemap' => (bool) ($data['show_in_sitemap'] ?? true),
            'show_in_feed' => (bool) ($data['show_in_feed'] ?? false),
            'breadcrumbs_enabled' => (bool) ($data['breadcrumbs_enabled'] ?? true),
            'show_in_navigation' => (bool) ($data['show_in_navigation'] ?? true),
            'sort_order' => (int) ($data['sort_order'] ?? 400),
            'is_published' => (bool) ($data['is_published'] ?? true),
        ]);

        $this->syncStaticPages($site->fresh(['hosting', 'hostedPages']));

        return $page;
    }

    public function updateCustomPage(Site $site, HostedPage $page, array $data): HostedPage
    {
        $this->assertHosted($site);

        if ($page->site_id !== $site->id || !$page->isCustom()) {
            throw new RuntimeException('Unsupported hosted page.');
        }

        $originalPath = $page->path();

        $page->update([
            'slug' => $data['slug'],
            'title' => $data['title'],
            'navigation_label' => filled($data['navigation_label'] ?? null) ? $data['navigation_label'] : $data['title'],
            'body_html' => $this->sanitizer->sanitize($data['body_html'] ?? ''),
            'sections' => $this->normalizeHostedSections($data['sections'] ?? []),
            'meta_title' => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
            'canonical_url' => $data['canonical_url'] ?? null,
            'social_title' => $data['social_title'] ?? null,
            'social_description' => $data['social_description'] ?? null,
            'social_image_asset_id' => $data['social_image_asset_id'] ?? null,
            'social_image_url' => $data['social_image_url'] ?? null,
            'robots_noindex' => (bool) ($data['robots_noindex'] ?? false),
            'schema_enabled' => (bool) ($data['schema_enabled'] ?? true),
            'show_in_sitemap' => (bool) ($data['show_in_sitemap'] ?? true),
            'show_in_feed' => (bool) ($data['show_in_feed'] ?? false),
            'breadcrumbs_enabled' => (bool) ($data['breadcrumbs_enabled'] ?? true),
            'show_in_navigation' => (bool) ($data['show_in_navigation'] ?? true),
            'sort_order' => (int) ($data['sort_order'] ?? $page->sort_order ?? 400),
            'is_published' => (bool) ($data['is_published'] ?? true),
        ]);

        $updatedPath = $page->path();

        if ($originalPath !== $updatedPath) {
            $site->hostedRedirects()->firstOrCreate(
                ['source_path' => $this->normalizeRedirectPath($originalPath)],
                [
                    'destination_url' => $this->normalizeRedirectDestination($updatedPath),
                    'http_status' => HostedRedirect::STATUS_PERMANENT,
                ],
            );
        }

        $this->syncStaticPages($site->fresh(['hosting', 'hostedPages']));

        return $page->fresh();
    }

    public function deleteCustomPage(Site $site, HostedPage $page): void
    {
        $this->assertHosted($site);

        if ($page->site_id !== $site->id || !$page->isCustom()) {
            throw new RuntimeException('Unsupported hosted page.');
        }

        $page->delete();

        $this->syncStaticPages($site->fresh(['hosting', 'hostedPages']));
    }

    public function createRedirect(Site $site, array $data): HostedRedirect
    {
        $this->assertHosted($site);

        /** @var HostedRedirect $redirect */
        $redirect = $site->hostedRedirects()->create([
            'source_path' => $this->normalizeRedirectPath($data['source_path']),
            'destination_url' => $this->normalizeRedirectDestination($data['destination_url']),
            'http_status' => (int) ($data['http_status'] ?? HostedRedirect::STATUS_PERMANENT),
        ]);

        return $redirect;
    }

    public function createNavigationItem(Site $site, array $data): HostedNavigationItem
    {
        $this->assertHosted($site);

        /** @var HostedNavigationItem $item */
        $item = $site->hostedNavigationItems()->create([
            'placement' => $data['placement'],
            'type' => $data['type'],
            'label' => $data['label'],
            'path' => $data['type'] === HostedNavigationItem::TYPE_PATH ? $this->normalizeNavigationPath($data['path']) : null,
            'url' => $data['type'] === HostedNavigationItem::TYPE_URL ? trim((string) $data['url']) : null,
            'open_in_new_tab' => (bool) ($data['open_in_new_tab'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'sort_order' => (int) ($data['sort_order'] ?? 100),
        ]);

        return $item->fresh();
    }

    public function updateNavigationItem(Site $site, HostedNavigationItem $item, array $data): HostedNavigationItem
    {
        $this->assertHosted($site);

        if ($item->site_id !== $site->id) {
            throw new RuntimeException('Unsupported hosted navigation item.');
        }

        $item->update([
            'placement' => $data['placement'],
            'type' => $data['type'],
            'label' => $data['label'],
            'path' => $data['type'] === HostedNavigationItem::TYPE_PATH ? $this->normalizeNavigationPath($data['path']) : null,
            'url' => $data['type'] === HostedNavigationItem::TYPE_URL ? trim((string) $data['url']) : null,
            'open_in_new_tab' => (bool) ($data['open_in_new_tab'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'sort_order' => (int) ($data['sort_order'] ?? $item->sort_order ?? 100),
        ]);

        return $item->fresh();
    }

    public function deleteNavigationItem(Site $site, HostedNavigationItem $item): void
    {
        $this->assertHosted($site);

        if ($item->site_id !== $site->id) {
            throw new RuntimeException('Unsupported hosted navigation item.');
        }

        $item->delete();
    }

    public function createAuthor(Site $site, array $data): HostedAuthor
    {
        $this->assertHosted($site);

        /** @var HostedAuthor $author */
        $author = $site->hostedAuthors()->create([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'bio' => $data['bio'] ?? null,
            'avatar_url' => $data['avatar_url'] ?? null,
            'sort_order' => (int) ($data['sort_order'] ?? 100),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        $this->syncStaticPages($site->fresh(['hosting', 'hostedPages', 'hostedAuthors', 'hostedCategories', 'hostedTags']));

        return $author->fresh();
    }

    public function updateAuthor(Site $site, HostedAuthor $author, array $data): HostedAuthor
    {
        $this->assertHosted($site);

        if ($author->site_id !== $site->id) {
            throw new RuntimeException('Unsupported hosted author.');
        }

        $author->update([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'bio' => $data['bio'] ?? null,
            'avatar_url' => $data['avatar_url'] ?? null,
            'sort_order' => (int) ($data['sort_order'] ?? $author->sort_order ?? 100),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        $this->syncStaticPages($site->fresh(['hosting', 'hostedPages', 'hostedAuthors', 'hostedCategories', 'hostedTags']));

        return $author->fresh();
    }

    public function deleteAuthor(Site $site, HostedAuthor $author): void
    {
        $this->assertHosted($site);

        if ($author->site_id !== $site->id) {
            throw new RuntimeException('Unsupported hosted author.');
        }

        $author->delete();

        $this->syncStaticPages($site->fresh(['hosting', 'hostedPages', 'hostedAuthors', 'hostedCategories', 'hostedTags']));
    }

    public function createCategory(Site $site, array $data): HostedCategory
    {
        $this->assertHosted($site);

        /** @var HostedCategory $category */
        $category = $site->hostedCategories()->create([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
            'sort_order' => (int) ($data['sort_order'] ?? 100),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        $this->syncStaticPages($site->fresh(['hosting', 'hostedPages', 'hostedAuthors', 'hostedCategories', 'hostedTags']));

        return $category->fresh();
    }

    public function updateCategory(Site $site, HostedCategory $category, array $data): HostedCategory
    {
        $this->assertHosted($site);

        if ($category->site_id !== $site->id) {
            throw new RuntimeException('Unsupported hosted category.');
        }

        $category->update([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
            'sort_order' => (int) ($data['sort_order'] ?? $category->sort_order ?? 100),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        $this->syncStaticPages($site->fresh(['hosting', 'hostedPages', 'hostedAuthors', 'hostedCategories', 'hostedTags']));

        return $category->fresh();
    }

    public function deleteCategory(Site $site, HostedCategory $category): void
    {
        $this->assertHosted($site);

        if ($category->site_id !== $site->id) {
            throw new RuntimeException('Unsupported hosted category.');
        }

        $category->delete();

        $this->syncStaticPages($site->fresh(['hosting', 'hostedPages', 'hostedAuthors', 'hostedCategories', 'hostedTags']));
    }

    public function createTag(Site $site, array $data): HostedTag
    {
        $this->assertHosted($site);

        /** @var HostedTag $tag */
        $tag = $site->hostedTags()->create([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'sort_order' => (int) ($data['sort_order'] ?? 100),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        $this->syncStaticPages($site->fresh(['hosting', 'hostedPages', 'hostedAuthors', 'hostedCategories', 'hostedTags']));

        return $tag->fresh();
    }

    public function updateTag(Site $site, HostedTag $tag, array $data): HostedTag
    {
        $this->assertHosted($site);

        if ($tag->site_id !== $site->id) {
            throw new RuntimeException('Unsupported hosted tag.');
        }

        $tag->update([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'sort_order' => (int) ($data['sort_order'] ?? $tag->sort_order ?? 100),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        $this->syncStaticPages($site->fresh(['hosting', 'hostedPages', 'hostedAuthors', 'hostedCategories', 'hostedTags']));

        return $tag->fresh();
    }

    public function deleteTag(Site $site, HostedTag $tag): void
    {
        $this->assertHosted($site);

        if ($tag->site_id !== $site->id) {
            throw new RuntimeException('Unsupported hosted tag.');
        }

        $tag->delete();

        $this->syncStaticPages($site->fresh(['hosting', 'hostedPages', 'hostedAuthors', 'hostedCategories', 'hostedTags']));
    }

    public function createAssetFromUpload(Site $site, UploadedFile $file, array $data): HostedAsset
    {
        $this->assertHosted($site);

        $path = $this->storeHostedAssetFile(
            $site,
            $file->getClientOriginalExtension() ?: $file->extension(),
            (string) file_get_contents($file->getRealPath())
        );

        /** @var HostedAsset $asset */
        $asset = $site->hostedAssets()->create([
            'type' => $data['type'] ?? HostedAsset::TYPE_IMAGE,
            'name' => $data['name'] ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) ?: 'Asset',
            'disk' => 'public',
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'alt_text' => $data['alt_text'] ?? null,
            'source_url' => null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        return $asset->fresh();
    }

    public function createAssetFromRemoteUrl(Site $site, string $sourceUrl, array $data): HostedAsset
    {
        $this->assertHosted($site);

        $response = Http::timeout(30)->get($sourceUrl);

        if (!$response->successful()) {
            throw new RuntimeException('Unable to download the remote asset.');
        }

        $pathInfo = pathinfo(parse_url($sourceUrl, PHP_URL_PATH) ?: '');
        $extension = $pathInfo['extension'] ?? 'bin';
        $path = $this->storeHostedAssetFile($site, $extension, $response->body());

        /** @var HostedAsset $asset */
        $asset = $site->hostedAssets()->create([
            'type' => $data['type'] ?? HostedAsset::TYPE_IMAGE,
            'name' => $data['name'] ?? ($pathInfo['filename'] ?? 'Remote asset'),
            'disk' => 'public',
            'path' => $path,
            'mime_type' => $response->header('Content-Type'),
            'size_bytes' => strlen($response->body()),
            'alt_text' => $data['alt_text'] ?? null,
            'source_url' => $sourceUrl,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        return $asset->fresh();
    }

    public function updateAsset(Site $site, HostedAsset $asset, array $data): HostedAsset
    {
        $this->assertHosted($site);

        if ($asset->site_id !== $site->id) {
            throw new RuntimeException('Unsupported hosted asset.');
        }

        $asset->update([
            'type' => $data['type'] ?? $asset->type,
            'name' => $data['name'] ?? $asset->name,
            'alt_text' => $data['alt_text'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? $asset->is_active),
        ]);

        return $asset->fresh();
    }

    public function deleteAsset(Site $site, HostedAsset $asset): void
    {
        $this->assertHosted($site);

        if ($asset->site_id !== $site->id) {
            throw new RuntimeException('Unsupported hosted asset.');
        }

        Storage::disk($asset->disk)->delete($asset->path);

        $hosting = $site->hosting;
        if ($hosting) {
            $themeSettings = $hosting->theme_settings ?? [];
            $dirty = false;

            foreach (['logo_asset_id', 'social_image_asset_id'] as $key) {
                if (($themeSettings[$key] ?? null) === $asset->id) {
                    $themeSettings[$key] = null;
                    $dirty = true;
                }
            }

            if ($dirty) {
                $hosting->update([
                    'theme_settings' => array_replace_recursive(
                        $this->pageGenerator->defaultTheme($site),
                        $themeSettings,
                    ),
                ]);
            }
        }

        $asset->delete();
    }

    public function updateRedirect(Site $site, HostedRedirect $redirect, array $data): HostedRedirect
    {
        $this->assertHosted($site);

        if ($redirect->site_id !== $site->id) {
            throw new RuntimeException('Unsupported hosted redirect.');
        }

        $redirect->update([
            'source_path' => $this->normalizeRedirectPath($data['source_path']),
            'destination_url' => $this->normalizeRedirectDestination($data['destination_url']),
            'http_status' => (int) ($data['http_status'] ?? HostedRedirect::STATUS_PERMANENT),
        ]);

        return $redirect->fresh();
    }

    public function deleteRedirect(Site $site, HostedRedirect $redirect): void
    {
        $this->assertHosted($site);

        if ($redirect->site_id !== $site->id) {
            throw new RuntimeException('Unsupported hosted redirect.');
        }

        $redirect->delete();
    }

    public function normalizeRedirectPath(string $path): string
    {
        $normalized = '/' . ltrim(trim($path), '/');
        $normalized = rtrim($normalized, '/');

        return $normalized === '' ? '/' : $normalized;
    }

    public function normalizeRedirectDestination(string $destination): string
    {
        $destination = trim($destination);

        if (str_starts_with($destination, '/')) {
            return $this->normalizeRedirectPath($destination);
        }

        return $destination;
    }

    public function normalizeNavigationPath(string $path): string
    {
        $path = '/' . ltrim(trim($path), '/');
        $normalized = rtrim($path, '/');

        if ($path === '//' || $normalized === '') {
            return '/';
        }

        return $normalized;
    }

    protected function normalizeHostedSections(array $sections): array
    {
        return collect($sections)
            ->map(fn ($section) => is_array($section) ? $this->normalizeHostedSection($section) : null)
            ->filter()
            ->values()
            ->all();
    }

    protected function normalizeHostedSection(array $section): ?array
    {
        $type = Arr::get($section, 'type');

        if (!in_array($type, HostedPage::SECTION_TYPES, true)) {
            return null;
        }

        return match ($type) {
            HostedPage::SECTION_RICH_TEXT => $this->normalizeRichTextSection($section),
            HostedPage::SECTION_CALLOUT => $this->normalizeCalloutSection($section),
            HostedPage::SECTION_FEATURE_GRID => $this->normalizeFeatureGridSection($section),
            HostedPage::SECTION_FAQ => $this->normalizeFaqSection($section),
            HostedPage::SECTION_HERO => $this->normalizeHeroSection($section),
            HostedPage::SECTION_TESTIMONIAL_GRID => $this->normalizeTestimonialGridSection($section),
            HostedPage::SECTION_STAT_GRID => $this->normalizeStatGridSection($section),
            default => null,
        };
    }

    protected function normalizeRichTextSection(array $section): ?array
    {
        $heading = $this->cleanSectionText(Arr::get($section, 'heading'), 255);
        $bodyHtml = $this->sanitizer->sanitize((string) Arr::get($section, 'body_html', ''));

        if (blank($heading) && blank(strip_tags($bodyHtml))) {
            return null;
        }

        return [
            'type' => HostedPage::SECTION_RICH_TEXT,
            'heading' => $heading,
            'body_html' => $bodyHtml,
        ];
    }

    protected function normalizeCalloutSection(array $section): ?array
    {
        $title = $this->cleanSectionText(Arr::get($section, 'title'), 255);
        $body = $this->cleanSectionText(Arr::get($section, 'body'), 3000);

        if (blank($title) && blank($body)) {
            return null;
        }

        return [
            'type' => HostedPage::SECTION_CALLOUT,
            'eyebrow' => $this->cleanSectionText(Arr::get($section, 'eyebrow'), 120),
            'title' => $title,
            'body' => $body,
            'cta_label' => $this->cleanSectionText(Arr::get($section, 'cta_label'), 120),
            'cta_href' => $this->normalizeSectionHref(Arr::get($section, 'cta_href')),
        ];
    }

    protected function normalizeHeroSection(array $section): ?array
    {
        $title = $this->cleanSectionText(Arr::get($section, 'title'), 255);
        $body = $this->cleanSectionText(Arr::get($section, 'body'), 3000);

        if (blank($title) && blank($body)) {
            return null;
        }

        return [
            'type' => HostedPage::SECTION_HERO,
            'eyebrow' => $this->cleanSectionText(Arr::get($section, 'eyebrow'), 120),
            'title' => $title,
            'body' => $body,
            'cta_label' => $this->cleanSectionText(Arr::get($section, 'cta_label'), 120),
            'cta_href' => $this->normalizeSectionHref(Arr::get($section, 'cta_href')),
            'secondary_cta_label' => $this->cleanSectionText(Arr::get($section, 'secondary_cta_label'), 120),
            'secondary_cta_href' => $this->normalizeSectionHref(Arr::get($section, 'secondary_cta_href')),
        ];
    }

    protected function normalizeFeatureGridSection(array $section): ?array
    {
        $items = collect(Arr::wrap(Arr::get($section, 'items')))
            ->map(function ($item) {
                if (!is_array($item)) {
                    return null;
                }

                $title = $this->cleanSectionText(Arr::get($item, 'title'), 255);
                $body = $this->cleanSectionText(Arr::get($item, 'body'), 2000);

                if (blank($title) && blank($body)) {
                    return null;
                }

                return [
                    'title' => $title,
                    'body' => $body,
                ];
            })
            ->filter()
            ->values()
            ->all();

        if ($items === []) {
            return null;
        }

        return [
            'type' => HostedPage::SECTION_FEATURE_GRID,
            'title' => $this->cleanSectionText(Arr::get($section, 'title'), 255),
            'items' => $items,
        ];
    }

    protected function normalizeFaqSection(array $section): ?array
    {
        $items = collect(Arr::wrap(Arr::get($section, 'items')))
            ->map(function ($item) {
                if (!is_array($item)) {
                    return null;
                }

                $question = $this->cleanSectionText(Arr::get($item, 'question'), 255);
                $answer = $this->cleanSectionText(Arr::get($item, 'answer'), 3000);

                if (blank($question) && blank($answer)) {
                    return null;
                }

                return [
                    'question' => $question,
                    'answer' => $answer,
                ];
            })
            ->filter()
            ->values()
            ->all();

        if ($items === []) {
            return null;
        }

        return [
            'type' => HostedPage::SECTION_FAQ,
            'title' => $this->cleanSectionText(Arr::get($section, 'title'), 255),
            'items' => $items,
        ];
    }

    protected function normalizeTestimonialGridSection(array $section): ?array
    {
        $items = collect(Arr::wrap(Arr::get($section, 'items')))
            ->map(function ($item) {
                if (!is_array($item)) {
                    return null;
                }

                $author = $this->cleanSectionText(Arr::get($item, 'title'), 255);
                $quote = $this->cleanSectionText(Arr::get($item, 'body'), 2000);

                if (blank($author) && blank($quote)) {
                    return null;
                }

                return [
                    'title' => $author,
                    'body' => $quote,
                ];
            })
            ->filter()
            ->values()
            ->all();

        if ($items === []) {
            return null;
        }

        return [
            'type' => HostedPage::SECTION_TESTIMONIAL_GRID,
            'title' => $this->cleanSectionText(Arr::get($section, 'title'), 255),
            'items' => $items,
        ];
    }

    protected function normalizeStatGridSection(array $section): ?array
    {
        $items = collect(Arr::wrap(Arr::get($section, 'items')))
            ->map(function ($item) {
                if (!is_array($item)) {
                    return null;
                }

                $value = $this->cleanSectionText(Arr::get($item, 'title'), 120);
                $label = $this->cleanSectionText(Arr::get($item, 'body'), 255);

                if (blank($value) && blank($label)) {
                    return null;
                }

                return [
                    'title' => $value,
                    'body' => $label,
                ];
            })
            ->filter()
            ->values()
            ->all();

        if ($items === []) {
            return null;
        }

        return [
            'type' => HostedPage::SECTION_STAT_GRID,
            'title' => $this->cleanSectionText(Arr::get($section, 'title'), 255),
            'items' => $items,
        ];
    }

    protected function normalizeSectionHref(mixed $value): ?string
    {
        $href = trim((string) $value);

        if ($href === '') {
            return null;
        }

        if (str_starts_with($href, '/')) {
            return $this->normalizeNavigationPath($href);
        }

        return filter_var($href, FILTER_VALIDATE_URL) ? $href : null;
    }

    protected function cleanSectionText(mixed $value, int $limit): ?string
    {
        $text = trim(strip_tags((string) $value));

        if ($text === '') {
            return null;
        }

        return mb_substr($text, 0, $limit);
    }

    public function recordArticlePage(Site $site, string $slug, string $title): void
    {
        $domain = $site->fresh(['hosting'])->getPrimaryHostedDomain();

        if (!$domain) {
            return;
        }

        $site->pages()->updateOrCreate(
            ['url' => "https://{$domain}/blog/{$slug}"],
            [
                'title' => $title,
                'source' => 'hosted',
                'last_seen_at' => now(),
            ]
        );
    }

    public function removeArticlePage(Site $site, string $slug): void
    {
        $domains = array_filter([
            $site->hosting?->staging_domain,
            $site->hosting?->custom_domain,
            $site->hosting?->canonical_domain,
        ]);

        foreach (array_unique($domains) as $domain) {
            $site->pages()->where('url', "https://{$domain}/blog/{$slug}")->delete();
        }
    }

    public function syncPublishedUrls(Site $site): void
    {
        $domain = $site->fresh(['hosting'])->getPrimaryHostedDomain();

        if (!$domain) {
            return;
        }

        foreach ($this->hostedDomains($site) as $knownDomain) {
            $site->pages()
                ->where('source', 'hosted')
                ->where('url', 'like', "https://{$knownDomain}/blog/%")
                ->delete();
        }

        $site->articles()
            ->where('status', Article::STATUS_PUBLISHED)
            ->get()
            ->each(function (Article $article) use ($domain) {
                $article->forceFill([
                    'published_url' => "https://{$domain}/blog/{$article->slug}",
                    'published_via' => 'hosted',
                ])->save();

                $site = $article->site()->first();

                if ($site) {
                    $this->recordArticlePage($site->loadMissing('hosting'), $article->slug, $article->title);
                }
            });
    }

    public function syncStaticPages(Site $site): void
    {
        $site->loadMissing(['hosting', 'hostedPages', 'hostedAuthors', 'hostedCategories', 'hostedTags']);
        $domains = $this->hostedDomains($site->fresh(['hosting']));

        if ($domains === []) {
            return;
        }

        foreach ($domains as $domain) {
            $site->pages()
                ->where('source', 'hosted')
                ->where(function ($query) use ($domain) {
                    $query
                        ->where('url', "https://{$domain}/")
                        ->orWhere('url', "https://{$domain}/blog")
                        ->orWhere('url', 'like', "https://{$domain}/authors/%")
                        ->orWhere('url', 'like', "https://{$domain}/categories/%")
                        ->orWhere('url', 'like', "https://{$domain}/tags/%")
                        ->orWhere(function ($nested) use ($domain) {
                            $nested
                                ->where('url', 'like', "https://{$domain}/%")
                                ->where('url', 'not like', "https://{$domain}/blog/%");
                        });
                })
                ->delete();

            $pages = [
                ['url' => "https://{$domain}/", 'title' => $site->name],
                ['url' => "https://{$domain}/blog", 'title' => "{$site->name} blog"],
            ];

            foreach ($site->hostedPages as $page) {
                if (!$page->is_published || $page->kind === HostedPage::KIND_HOME) {
                    continue;
                }

                $pages[] = [
                    'url' => "https://{$domain}{$page->path()}",
                    'title' => $page->title,
                ];
            }

            foreach ($this->publishedArchivePages($site) as $archivePage) {
                $pages[] = [
                    'url' => "https://{$domain}{$archivePage['path']}",
                    'title' => $archivePage['title'],
                ];
            }

            foreach ($pages as $page) {
                $site->pages()->updateOrCreate(
                    ['url' => $page['url']],
                    [
                        'title' => $page['title'],
                        'source' => 'hosted',
                        'last_seen_at' => now(),
                    ]
                );
            }
        }
    }

    public function expectedDnsRecords(string $domain): array
    {
        $domain = $this->normalizeDomain($domain);
        $parts = array_values(array_filter(explode('.', $domain)));
        $isSubdomain = count($parts) > 2;

        if ($isSubdomain) {
            return [
                'type' => 'CNAME',
                'value' => config('services.hosted.cname_target'),
            ];
        }

        return [
            'type' => 'A/AAAA',
            'value' => config('services.hosted.public_ip'),
        ];
    }

    public function checkDns(string $domain): array
    {
        $domain = $this->normalizeDomain($domain);
        $expected = $this->expectedDnsRecords($domain);
        $records = [];
        $matched = false;

        if ($expected['type'] === 'CNAME') {
            $cnameRecords = dns_get_record($domain, DNS_CNAME) ?: [];
            $records = array_map(fn (array $record) => $record['target'] ?? null, $cnameRecords);
            $target = rtrim((string) ($expected['value'] ?? ''), '.');
            $matched = in_array($target, array_map(fn ($value) => rtrim((string) $value, '.'), $records), true);
        } else {
            $aRecords = dns_get_record($domain, DNS_A) ?: [];
            $aaaaRecords = dns_get_record($domain, DNS_AAAA) ?: [];
            $records = [
                ...array_map(fn (array $record) => $record['ip'] ?? null, $aRecords),
                ...array_map(fn (array $record) => $record['ipv6'] ?? null, $aaaaRecords),
            ];

            $expectedIp = (string) ($expected['value'] ?? '');
            $matched = $expectedIp !== '' && in_array($expectedIp, $records, true);
        }

        return [
            'domain' => $domain,
            'expected' => $expected,
            'records' => array_values(array_filter($records)),
            'matched' => $matched,
        ];
    }

    public function hostedSiteExportPath(Site $site): string
    {
        return storage_path("app/exports/sites/site-{$site->id}.zip");
    }

    private function assertHosted(Site $site): void
    {
        if (!$site->isHosted()) {
            throw new RuntimeException('This operation is only available for hosted sites.');
        }
    }

    private function systemPageDefaults(string $kind): array
    {
        return match ($kind) {
            HostedPage::KIND_HOME => [
                'slug' => 'home',
                'show_in_navigation' => true,
                'show_in_sitemap' => true,
                'sort_order' => 0,
            ],
            HostedPage::KIND_ABOUT => [
                'slug' => 'about',
                'show_in_navigation' => true,
                'show_in_sitemap' => true,
                'sort_order' => 200,
            ],
            HostedPage::KIND_LEGAL => [
                'slug' => 'legal',
                'show_in_navigation' => false,
                'show_in_sitemap' => false,
                'sort_order' => 900,
            ],
            default => throw new RuntimeException('Unsupported hosted page kind.'),
        };
    }

    private function hostedDomains(Site $site): array
    {
        return array_values(array_unique(array_filter([
            $site->hosting?->staging_domain,
            $site->hosting?->custom_domain,
            $site->hosting?->canonical_domain,
        ])));
    }

    private function publishedArchivePages(Site $site): array
    {
        $publishedArticleIds = $site->articles()
            ->where('status', Article::STATUS_PUBLISHED)
            ->pluck('id');

        if ($publishedArticleIds->isEmpty()) {
            return [];
        }

        $pages = [];

        $authorIds = $site->articles()
            ->whereIn('id', $publishedArticleIds)
            ->whereNotNull('hosted_author_id')
            ->pluck('hosted_author_id')
            ->unique();

        foreach ($site->hostedAuthors->whereIn('id', $authorIds)->where('is_active', true) as $author) {
            $pages[] = [
                'path' => $author->archivePath(),
                'title' => "{$author->name} articles",
            ];
        }

        $categoryIds = $site->articles()
            ->whereIn('id', $publishedArticleIds)
            ->whereNotNull('hosted_category_id')
            ->pluck('hosted_category_id')
            ->unique();

        foreach ($site->hostedCategories->whereIn('id', $categoryIds)->where('is_active', true) as $category) {
            $pages[] = [
                'path' => $category->archivePath(),
                'title' => "{$category->name} articles",
            ];
        }

        $tagIds = DB::table('article_hosted_tag')
            ->join('articles', 'articles.id', '=', 'article_hosted_tag.article_id')
            ->where('articles.site_id', $site->id)
            ->where('articles.status', Article::STATUS_PUBLISHED)
            ->pluck('article_hosted_tag.hosted_tag_id')
            ->unique();

        foreach ($site->hostedTags->whereIn('id', $tagIds)->where('is_active', true) as $tag) {
            $pages[] = [
                'path' => $tag->archivePath(),
                'title' => "{$tag->name} articles",
            ];
        }

        return $pages;
    }

    private function generateStagingDomain(Site $site): string
    {
        $base = config('services.hosted.staging_base_domain');

        if (!$base) {
            throw new RuntimeException('HOSTED_STAGING_BASE_DOMAIN is not configured.');
        }

        $slug = Str::slug($site->name);
        $slug = $slug !== '' ? $slug : 'site';
        $suffix = substr(md5((string) $site->id), 0, 8);

        return "{$slug}-{$suffix}.{$base}";
    }

    private function normalizeDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('#^https?://#', '', $domain);
        $domain = rtrim($domain, '/');

        return $domain;
    }

    private function webhookUrl(): string
    {
        return route('webhooks.ploi.tenant-certificate', [
            'token' => config('services.ploi.webhook_token'),
        ]);
    }

    private function storeHostedAssetFile(Site $site, string $extension, string $contents): string
    {
        $extension = trim(strtolower($extension), '.');
        $extension = $extension !== '' ? $extension : 'bin';
        $path = sprintf(
            'hosted-assets/site-%d/%s.%s',
            $site->id,
            Str::uuid(),
            $extension,
        );

        Storage::disk('public')->put($path, $contents);

        return $path;
    }
}
