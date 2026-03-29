<?php

namespace App\Services\Hosted;

use App\Models\Article;
use App\Models\HostedPage;
use App\Models\Integration;
use App\Models\Site;
use App\Models\SiteHosting;
use App\Models\Team;
use App\Services\Hosting\PloiTenantService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

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

        $this->ploi->createTenant($stagingDomain);
        $this->ploi->requestCertificate($stagingDomain, [$stagingDomain], $this->webhookUrl());

        $hosting->update([
            'staging_domain' => $stagingDomain,
            'canonical_domain' => $stagingDomain,
            'ssl_status' => SiteHosting::SSL_STATUS_PENDING,
            'staging_certificate_requested_at' => now(),
        ]);

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

            return $verification;
        }

        $this->ploi->createTenant($hosting->custom_domain);
        $this->ploi->requestCertificate(
            $hosting->custom_domain,
            [$hosting->custom_domain],
            $this->webhookUrl()
        );

        $hosting->update([
            'domain_status' => SiteHosting::DOMAIN_STATUS_SSL_PENDING,
            'ssl_status' => SiteHosting::SSL_STATUS_PENDING,
            'custom_domain_verified_at' => now(),
            'custom_certificate_requested_at' => now(),
            'last_error' => null,
        ]);

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
        }

        return $hosting->fresh();
    }

    public function updateTheme(Site $site, array $themeSettings, ?string $templateKey = null): SiteHosting
    {
        $this->assertHosted($site);
        $hosting = $site->hosting()->firstOrFail();
        $defaults = $this->pageGenerator->defaultTheme($site);

        $hosting->update([
            'template_key' => $templateKey ?: $hosting->template_key,
            'theme_settings' => array_replace_recursive($defaults, $hosting->theme_settings ?? [], $themeSettings),
        ]);

        return $hosting->fresh();
    }

    public function updatePage(Site $site, string $kind, array $data): HostedPage
    {
        $this->assertHosted($site);

        if (!in_array($kind, HostedPage::KINDS, true)) {
            throw new RuntimeException('Unsupported hosted page kind.');
        }

        /** @var HostedPage $page */
        $page = $site->hostedPages()->updateOrCreate(
            ['kind' => $kind],
            [
                'title' => $data['title'],
                'body_html' => $this->sanitizer->sanitize($data['body_html'] ?? ''),
                'meta_title' => $data['meta_title'] ?? null,
                'meta_description' => $data['meta_description'] ?? null,
                'is_published' => (bool) ($data['is_published'] ?? true),
            ]
        );

        $this->syncStaticPages($site->fresh(['hosting', 'hostedPages']));

        return $page;
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

        $site->pages()
            ->where('source', 'hosted')
            ->where('url', 'like', 'https://%/blog/%')
            ->delete();

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
        $domain = $site->fresh(['hosting'])->getPrimaryHostedDomain();

        if (!$domain) {
            return;
        }

        $site->pages()
            ->where('source', 'hosted')
            ->whereIn('url', [
                "https://{$domain}/",
                "https://{$domain}/blog",
                "https://{$domain}/about",
                "https://{$domain}/legal",
            ])
            ->delete();

        $pages = [
            ['url' => "https://{$domain}/", 'title' => $site->name],
            ['url' => "https://{$domain}/blog", 'title' => "{$site->name} blog"],
        ];

        foreach ($site->hostedPages as $page) {
            if (!$page->is_published) {
                continue;
            }

            $path = match ($page->kind) {
                HostedPage::KIND_HOME => '/',
                HostedPage::KIND_ABOUT => '/about',
                HostedPage::KIND_LEGAL => '/legal',
                default => null,
            };

            if ($path) {
                $pages[] = [
                    'url' => "https://{$domain}{$path}",
                    'title' => $page->title,
                ];
            }
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
}
