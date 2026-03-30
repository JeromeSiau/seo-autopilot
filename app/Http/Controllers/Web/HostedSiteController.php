<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Resources\HostedDeployEventResource;
use App\Http\Resources\HostedExportRunResource;
use App\Jobs\GenerateHostedSiteExportJob;
use App\Models\Article;
use App\Models\HostedAuthor;
use App\Models\HostedAsset;
use App\Models\HostedCategory;
use App\Models\HostedDeployEvent;
use App\Models\HostedExportRun;
use App\Models\HostedNavigationItem;
use App\Models\HostedPage;
use App\Models\HostedRedirect;
use App\Models\HostedTag;
use App\Models\Site;
use App\Services\Hosted\HostedExportService;
use App\Services\Hosted\HostedSiteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class HostedSiteController extends Controller
{
    private const RESERVED_CUSTOM_PAGE_SLUGS = [
        'about',
        'analytics',
        'articles',
        'auth',
        'billing',
        'blog',
        'confirm-password',
        'content-plans',
        'dashboard',
        'en',
        'es',
        'fr',
        'integrations',
        'invitations',
        'keywords',
        'legal',
        'login',
        'notifications',
        'onboarding',
        'profile',
        'register',
        'reset-password',
        'settings',
        'sites',
        'stripe',
        'teams',
        'verify-email',
    ];

    public function __construct(
        private readonly HostedSiteService $hosting,
        private readonly HostedExportService $exports,
    ) {}

    public function show(Site $site): Response
    {
        $this->authorize('view', $site);
        abort_unless($site->isHosted(), 404);

        $site->load([
            'hosting',
            'activeIntegration',
            'hostedPages' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
            'hostedRedirects' => fn ($query) => $query->orderBy('source_path'),
            'hostedAuthors' => fn ($query) => $query->orderBy('sort_order')->orderBy('name'),
            'hostedCategories' => fn ($query) => $query->orderBy('sort_order')->orderBy('name'),
            'hostedTags' => fn ($query) => $query->orderBy('sort_order')->orderBy('name'),
            'hostedAssets' => fn ($query) => $query->latest(),
            'hostedNavigationItems' => fn ($query) => $query->orderBy('placement')->orderBy('sort_order')->orderBy('id'),
        ]);
        $site->hosting?->load([
            'exportRuns' => fn ($query) => $query->latest('created_at')->limit(6),
            'deployEvents' => fn ($query) => $query->latest('occurred_at')->limit(10),
        ]);

        $dnsCheck = null;

        if ($site->hosting?->custom_domain) {
            $dnsCheck = rescue(
                fn () => $this->hosting->checkDns($site->hosting->custom_domain),
                report: false
            );
        }

        return Inertia::render('Sites/Hosting', [
            'site' => [
                ...(new \App\Http\Resources\SiteResource($site))->toArray(request()),
                'hosting' => $site->hosting,
                'hosted_export_runs' => HostedExportRunResource::collection($site->hosting?->exportRuns ?? collect())->resolve(),
                'hosted_deploy_events' => HostedDeployEventResource::collection($site->hosting?->deployEvents ?? collect())->resolve(),
                'hosting_health' => $this->buildHostingHealth($site, $dnsCheck),
                'hosted_pages' => $site->hostedPages,
                'site_export_available' => File::exists($this->hosting->hostedSiteExportPath($site)),
                'dns_expectation' => $site->hosting?->custom_domain
                    ? $this->hosting->expectedDnsRecords($site->hosting->custom_domain)
                    : null,
            ],
        ]);
    }

    public function provisionStaging(Site $site): RedirectResponse
    {
        $this->authorize('update', $site);
        $this->hosting->provisionStaging($site);

        return back()->with('success', 'Staging domain provisioned.');
    }

    public function storeDomain(Request $request, Site $site): RedirectResponse
    {
        $this->authorize('update', $site);

        $validated = $request->validate([
            'custom_domain' => ['required', 'string', 'max:255'],
        ]);

        $this->hosting->updateCustomDomain($site, $validated['custom_domain']);

        return back()->with('success', 'Custom domain saved.');
    }

    public function verifyDns(Site $site): RedirectResponse
    {
        $this->authorize('update', $site);
        $result = $this->hosting->verifyCustomDomain($site);

        return back()->with(
            $result['matched'] ? 'success' : 'error',
            $result['matched'] ? 'DNS verified and certificate request started.' : 'DNS does not match the expected target yet.'
        );
    }

    public function updateTheme(Request $request, Site $site): RedirectResponse
    {
        $this->authorize('update', $site);

        $validated = $request->validate([
            'template_key' => ['required', Rule::in(['editorial', 'magazine', 'minimal'])],
            'theme_settings' => ['required', 'array'],
            'theme_settings.logo_asset_id' => ['nullable', 'integer'],
            'theme_settings.social_image_asset_id' => ['nullable', 'integer'],
        ]);

        $this->hosting->updateTheme($site, $validated['theme_settings'], $validated['template_key']);

        return back()->with('success', 'Theme updated.');
    }

    public function updatePage(Request $request, Site $site, string $kind): RedirectResponse
    {
        $this->authorize('update', $site);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'navigation_label' => ['nullable', 'string', 'max:255'],
            'body_html' => ['nullable', 'string'],
            'sections' => ['nullable', 'array'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'canonical_url' => ['nullable', 'url', 'max:2048'],
            'social_title' => ['nullable', 'string', 'max:255'],
            'social_description' => ['nullable', 'string', 'max:500'],
            'social_image_asset_id' => ['nullable', 'integer'],
            'social_image_url' => ['nullable', 'url', 'max:2048'],
            'robots_noindex' => ['sometimes', 'boolean'],
            'schema_enabled' => ['sometimes', 'boolean'],
            'show_in_sitemap' => ['sometimes', 'boolean'],
            'show_in_feed' => ['sometimes', 'boolean'],
            'breadcrumbs_enabled' => ['sometimes', 'boolean'],
            'show_in_navigation' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'is_published' => ['required', 'boolean'],
        ]);

        $this->ensureHostedAssetBelongsToSite($site, $validated['social_image_asset_id'] ?? null, 'social_image_asset_id');

        $this->hosting->updatePage($site, $kind, $validated);

        return back()->with('success', 'Hosted page updated.');
    }

    public function storeCustomPage(Request $request, Site $site): RedirectResponse
    {
        $this->authorize('update', $site);

        $validated = $this->validateCustomPage($request, $site);

        $this->hosting->createCustomPage($site, $validated);

        return back()->with('success', 'Custom hosted page added.');
    }

    public function updateCustomPage(Request $request, Site $site, HostedPage $hostedPage): RedirectResponse
    {
        $this->authorize('update', $site);
        abort_unless($hostedPage->site_id === $site->id && $hostedPage->isCustom(), 404);

        $validated = $this->validateCustomPage($request, $site, $hostedPage);

        $this->hosting->updateCustomPage($site, $hostedPage, $validated);

        return back()->with('success', 'Custom hosted page updated.');
    }

    public function destroyCustomPage(Site $site, HostedPage $hostedPage): RedirectResponse
    {
        $this->authorize('update', $site);
        abort_unless($hostedPage->site_id === $site->id && $hostedPage->isCustom(), 404);

        $this->hosting->deleteCustomPage($site, $hostedPage);

        return back()->with('success', 'Custom hosted page deleted.');
    }

    public function storeRedirect(Request $request, Site $site): RedirectResponse
    {
        $this->authorize('update', $site);

        $validated = $this->validateRedirect($request, $site);

        $this->hosting->createRedirect($site, $validated);

        return back()->with('success', 'Hosted redirect added.');
    }

    public function storeNavigationItem(Request $request, Site $site): RedirectResponse
    {
        $this->authorize('update', $site);
        abort_unless($site->isHosted(), 404);

        $validated = $this->validateNavigationItem($request);
        $this->hosting->createNavigationItem($site, $validated);

        return back()->with('success', 'Hosted navigation item added.');
    }

    public function updateNavigationItem(Request $request, Site $site, HostedNavigationItem $hostedNavigationItem): RedirectResponse
    {
        $this->authorize('update', $site);
        abort_unless($hostedNavigationItem->site_id === $site->id, 404);

        $validated = $this->validateNavigationItem($request);
        $this->hosting->updateNavigationItem($site, $hostedNavigationItem, $validated);

        return back()->with('success', 'Hosted navigation item updated.');
    }

    public function destroyNavigationItem(Site $site, HostedNavigationItem $hostedNavigationItem): RedirectResponse
    {
        $this->authorize('update', $site);
        abort_unless($hostedNavigationItem->site_id === $site->id, 404);

        $this->hosting->deleteNavigationItem($site, $hostedNavigationItem);

        return back()->with('success', 'Hosted navigation item deleted.');
    }

    public function updateRedirect(Request $request, Site $site, HostedRedirect $hostedRedirect): RedirectResponse
    {
        $this->authorize('update', $site);
        abort_unless($hostedRedirect->site_id === $site->id, 404);

        $validated = $this->validateRedirect($request, $site, $hostedRedirect);

        $this->hosting->updateRedirect($site, $hostedRedirect, $validated);

        return back()->with('success', 'Hosted redirect updated.');
    }

    public function destroyRedirect(Site $site, HostedRedirect $hostedRedirect): RedirectResponse
    {
        $this->authorize('update', $site);
        abort_unless($hostedRedirect->site_id === $site->id, 404);

        $this->hosting->deleteRedirect($site, $hostedRedirect);

        return back()->with('success', 'Hosted redirect deleted.');
    }

    public function storeAuthor(Request $request, Site $site): RedirectResponse
    {
        $this->authorize('update', $site);

        $validated = $this->validateHostedAuthor($request, $site);

        $this->hosting->createAuthor($site, $validated);

        return back()->with('success', 'Hosted author added.');
    }

    public function updateAuthor(Request $request, Site $site, HostedAuthor $hostedAuthor): RedirectResponse
    {
        $this->authorize('update', $site);
        abort_unless($hostedAuthor->site_id === $site->id, 404);

        $validated = $this->validateHostedAuthor($request, $site, $hostedAuthor);

        $this->hosting->updateAuthor($site, $hostedAuthor, $validated);

        return back()->with('success', 'Hosted author updated.');
    }

    public function destroyAuthor(Site $site, HostedAuthor $hostedAuthor): RedirectResponse
    {
        $this->authorize('update', $site);
        abort_unless($hostedAuthor->site_id === $site->id, 404);

        $this->hosting->deleteAuthor($site, $hostedAuthor);

        return back()->with('success', 'Hosted author deleted.');
    }

    public function storeCategory(Request $request, Site $site): RedirectResponse
    {
        $this->authorize('update', $site);

        $validated = $this->validateHostedCategory($request, $site);

        $this->hosting->createCategory($site, $validated);

        return back()->with('success', 'Hosted category added.');
    }

    public function updateCategory(Request $request, Site $site, HostedCategory $hostedCategory): RedirectResponse
    {
        $this->authorize('update', $site);
        abort_unless($hostedCategory->site_id === $site->id, 404);

        $validated = $this->validateHostedCategory($request, $site, $hostedCategory);

        $this->hosting->updateCategory($site, $hostedCategory, $validated);

        return back()->with('success', 'Hosted category updated.');
    }

    public function destroyCategory(Site $site, HostedCategory $hostedCategory): RedirectResponse
    {
        $this->authorize('update', $site);
        abort_unless($hostedCategory->site_id === $site->id, 404);

        $this->hosting->deleteCategory($site, $hostedCategory);

        return back()->with('success', 'Hosted category deleted.');
    }

    public function storeTag(Request $request, Site $site): RedirectResponse
    {
        $this->authorize('update', $site);

        $validated = $this->validateHostedTag($request, $site);

        $this->hosting->createTag($site, $validated);

        return back()->with('success', 'Hosted tag added.');
    }

    public function updateTag(Request $request, Site $site, HostedTag $hostedTag): RedirectResponse
    {
        $this->authorize('update', $site);
        abort_unless($hostedTag->site_id === $site->id, 404);

        $validated = $this->validateHostedTag($request, $site, $hostedTag);

        $this->hosting->updateTag($site, $hostedTag, $validated);

        return back()->with('success', 'Hosted tag updated.');
    }

    public function destroyTag(Site $site, HostedTag $hostedTag): RedirectResponse
    {
        $this->authorize('update', $site);
        abort_unless($hostedTag->site_id === $site->id, 404);

        $this->hosting->deleteTag($site, $hostedTag);

        return back()->with('success', 'Hosted tag deleted.');
    }

    public function storeAsset(Request $request, Site $site): RedirectResponse
    {
        $this->authorize('update', $site);
        abort_unless($site->isHosted(), 404);

        $validated = $request->validate([
            'type' => ['required', Rule::in(HostedAsset::TYPES)],
            'name' => ['nullable', 'string', 'max:255'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'asset' => ['nullable', 'file', 'max:8192'],
            'source_url' => ['nullable', 'url', 'max:2048'],
        ]);

        if (!$request->hasFile('asset') && blank($validated['source_url'] ?? null)) {
            throw ValidationException::withMessages([
                'asset' => 'Upload a file or provide a source URL.',
            ]);
        }

        if ($request->hasFile('asset')) {
            $this->hosting->createAssetFromUpload($site, $request->file('asset'), $validated);
        } else {
            $this->hosting->createAssetFromRemoteUrl($site, $validated['source_url'], $validated);
        }

        return back()->with('success', 'Hosted asset uploaded.');
    }

    public function updateAsset(Request $request, Site $site, HostedAsset $hostedAsset): RedirectResponse
    {
        $this->authorize('update', $site);
        abort_unless($hostedAsset->site_id === $site->id, 404);

        $validated = $request->validate([
            'type' => ['required', Rule::in(HostedAsset::TYPES)],
            'name' => ['required', 'string', 'max:255'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $this->hosting->updateAsset($site, $hostedAsset, $validated);

        return back()->with('success', 'Hosted asset updated.');
    }

    public function destroyAsset(Site $site, HostedAsset $hostedAsset): RedirectResponse
    {
        $this->authorize('update', $site);
        abort_unless($hostedAsset->site_id === $site->id, 404);

        $this->hosting->deleteAsset($site, $hostedAsset);

        return back()->with('success', 'Hosted asset deleted.');
    }

    public function exportSite(Site $site): RedirectResponse
    {
        $this->authorize('view', $site);
        abort_unless($site->isHosted(), 404);

        $run = $this->hosting->queueSiteExport($site);
        GenerateHostedSiteExportJob::dispatch($site, $run->id);

        return back()->with('success', 'Site export generation started.');
    }

    public function downloadSiteExport(Site $site)
    {
        $this->authorize('view', $site);
        abort_unless($site->isHosted(), 404);

        $path = $this->hosting->hostedSiteExportPath($site);
        abort_unless(File::exists($path), 404);

        return response()->download($path, "site-{$site->id}-export.zip");
    }

    public function downloadArticleHtml(Article $article)
    {
        $this->authorize('view', $article);

        $html = $this->exports->renderArticleHtml($article->loadMissing(['site.hosting', 'site.hostedPages']));

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $article->slug . '.html"',
        ]);
    }

    private function validateCustomPage(Request $request, Site $site, ?HostedPage $hostedPage = null): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:150',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::notIn(self::RESERVED_CUSTOM_PAGE_SLUGS),
                Rule::unique('hosted_pages', 'slug')
                    ->where(fn ($query) => $query->where('site_id', $site->id))
                    ->ignore($hostedPage?->id),
            ],
            'navigation_label' => ['nullable', 'string', 'max:255'],
            'body_html' => ['nullable', 'string'],
            'sections' => ['nullable', 'array'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'canonical_url' => ['nullable', 'url', 'max:2048'],
            'social_title' => ['nullable', 'string', 'max:255'],
            'social_description' => ['nullable', 'string', 'max:500'],
            'social_image_asset_id' => ['nullable', 'integer'],
            'social_image_url' => ['nullable', 'url', 'max:2048'],
            'robots_noindex' => ['sometimes', 'boolean'],
            'schema_enabled' => ['sometimes', 'boolean'],
            'show_in_sitemap' => ['sometimes', 'boolean'],
            'show_in_feed' => ['sometimes', 'boolean'],
            'breadcrumbs_enabled' => ['sometimes', 'boolean'],
            'show_in_navigation' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'is_published' => ['required', 'boolean'],
        ]);

        $validated['slug'] = Str::slug($validated['slug']);
        $this->ensureHostedAssetBelongsToSite($site, $validated['social_image_asset_id'] ?? null, 'social_image_asset_id');

        return $validated;
    }

    private function validateNavigationItem(Request $request): array
    {
        return $request->validate([
            'placement' => ['required', Rule::in(HostedNavigationItem::PLACEMENTS)],
            'type' => ['required', Rule::in(HostedNavigationItem::TYPES)],
            'label' => ['required', 'string', 'max:255'],
            'path' => [
                'nullable',
                'string',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                    if ($request->input('type') !== HostedNavigationItem::TYPE_PATH) {
                        return;
                    }

                    $path = trim((string) $value);

                    if ($path === '') {
                        $fail('The path field is required for internal navigation items.');
                        return;
                    }

                    if (!preg_match('/^\/?[A-Za-z0-9\-\/]*$/', $path)) {
                        $fail('The path must be a valid internal path.');
                    }
                },
            ],
            'url' => [
                'nullable',
                'url',
                'max:2048',
                Rule::requiredIf(fn () => $request->input('type') === HostedNavigationItem::TYPE_URL),
            ],
            'open_in_new_tab' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
        ]);
    }

    private function validateRedirect(Request $request, Site $site, ?HostedRedirect $hostedRedirect = null): array
    {
        $validated = $request->validate([
            'source_path' => [
                'required',
                'string',
                'max:255',
                'regex:/^\/[A-Za-z0-9\-\/]*$/',
                Rule::unique('hosted_redirects', 'source_path')
                    ->where(fn ($query) => $query->where('site_id', $site->id))
                    ->ignore($hostedRedirect?->id),
            ],
            'destination_url' => [
                'required',
                'string',
                'max:2048',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $destination = trim((string) $value);

                    if (str_starts_with($destination, '/')) {
                        if (!preg_match('/^\/[A-Za-z0-9\-\/\?\=\&\_\.\#\%]*$/', $destination)) {
                            $fail('The destination path format is invalid.');
                        }

                        return;
                    }

                    if (!filter_var($destination, FILTER_VALIDATE_URL)) {
                        $fail('The destination must be an internal path or a valid URL.');
                    }
                },
            ],
            'http_status' => ['required', Rule::in(HostedRedirect::STATUSES)],
        ]);

        $validated['source_path'] = $this->hosting->normalizeRedirectPath($validated['source_path']);
        $validated['destination_url'] = $this->hosting->normalizeRedirectDestination($validated['destination_url']);

        if ($validated['destination_url'] === $validated['source_path']) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'destination_url' => 'The destination must be different from the source path.',
            ]);
        }

        return $validated;
    }

    private function validateHostedAuthor(Request $request, Site $site, ?HostedAuthor $hostedAuthor = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:150'],
            'bio' => ['nullable', 'string', 'max:4000'],
            'avatar_url' => ['nullable', 'string', 'max:2048'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $validated['slug'] = $this->normalizeHostedTaxonomySlug($validated['slug'] ?? $validated['name']);
        $this->ensureUniqueHostedTaxonomySlug('hosted_authors', $site, $validated['slug'], $hostedAuthor?->id);

        return $validated;
    }

    private function validateHostedCategory(Request $request, Site $site, ?HostedCategory $hostedCategory = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:4000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $validated['slug'] = $this->normalizeHostedTaxonomySlug($validated['slug'] ?? $validated['name']);
        $this->ensureUniqueHostedTaxonomySlug('hosted_categories', $site, $validated['slug'], $hostedCategory?->id);

        return $validated;
    }

    private function validateHostedTag(Request $request, Site $site, ?HostedTag $hostedTag = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:150'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $validated['slug'] = $this->normalizeHostedTaxonomySlug($validated['slug'] ?? $validated['name']);
        $this->ensureUniqueHostedTaxonomySlug('hosted_tags', $site, $validated['slug'], $hostedTag?->id);

        return $validated;
    }

    private function normalizeHostedTaxonomySlug(?string $value): string
    {
        $slug = Str::slug((string) $value);

        if ($slug === '') {
            throw ValidationException::withMessages([
                'slug' => 'A valid slug is required.',
            ]);
        }

        return $slug;
    }

    private function ensureUniqueHostedTaxonomySlug(string $table, Site $site, string $slug, ?int $ignoreId = null): void
    {
        $query = \Illuminate\Support\Facades\DB::table($table)
            ->where('site_id', $site->id)
            ->where('slug', $slug);

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'slug' => 'This slug is already in use for the site.',
            ]);
        }
    }

    private function ensureHostedAssetBelongsToSite(Site $site, ?int $assetId, string $field): void
    {
        if (!$assetId) {
            return;
        }

        $exists = $site->hostedAssets()
            ->where('id', $assetId)
            ->exists();

        if (!$exists) {
            throw ValidationException::withMessages([
                $field => 'The selected asset is invalid for this site.',
            ]);
        }
    }

    private function buildHostingHealth(Site $site, ?array $dnsCheck): array
    {
        $hosting = $site->hosting;
        $latestExport = $hosting?->exportRuns?->first();
        $latestEvent = $hosting?->deployEvents?->first();

        $checks = [
            [
                'key' => 'staging',
                'label' => 'Staging',
                'status' => match (true) {
                    !$hosting?->staging_domain => 'neutral',
                    $hosting->ssl_status === 'error' => 'critical',
                    in_array($hosting->ssl_status, ['pending'], true) => 'warning',
                    default => 'healthy',
                },
                'value' => $hosting?->staging_domain ?: 'Not provisioned',
                'detail' => $hosting?->staging_domain
                    ? sprintf('SSL: %s', ucfirst((string) ($hosting->ssl_status ?? 'none')))
                    : 'Provision a staging domain before going live.',
            ],
            [
                'key' => 'custom_domain',
                'label' => 'Custom domain',
                'status' => match (true) {
                    !$hosting?->custom_domain => 'neutral',
                    $dnsCheck && ($dnsCheck['matched'] ?? false) => 'healthy',
                    $hosting->domain_status === 'error' => 'critical',
                    in_array($hosting->domain_status, ['dns_pending', 'tenant_pending', 'ssl_pending'], true) => 'warning',
                    $hosting->domain_status === 'active' => 'healthy',
                    default => 'neutral',
                },
                'value' => $hosting?->custom_domain ?: 'Not connected',
                'detail' => $dnsCheck
                    ? (($dnsCheck['matched'] ?? false)
                        ? 'DNS matches the expected target.'
                        : 'Live DNS does not match the expected target yet.')
                    : 'Save a custom domain to run live DNS checks.',
            ],
            [
                'key' => 'exports',
                'label' => 'Exports',
                'status' => match ($latestExport?->status) {
                    HostedExportRun::STATUS_COMPLETED => 'healthy',
                    HostedExportRun::STATUS_FAILED => 'critical',
                    HostedExportRun::STATUS_PENDING, HostedExportRun::STATUS_RUNNING => 'warning',
                    default => 'neutral',
                },
                'value' => $latestExport
                    ? ucfirst($latestExport->status)
                    : 'No export history yet',
                'detail' => $latestExport?->completed_at
                    ? 'Last completed at ' . $latestExport->completed_at->toDateTimeString()
                    : ($latestExport?->started_at
                        ? 'Started at ' . $latestExport->started_at->toDateTimeString()
                        : 'Build a ZIP export to generate a snapshot.'),
            ],
            [
                'key' => 'deploy_events',
                'label' => 'Recent deploy activity',
                'status' => match ($latestEvent?->status) {
                    HostedDeployEvent::STATUS_ERROR => 'critical',
                    HostedDeployEvent::STATUS_WARNING => 'warning',
                    HostedDeployEvent::STATUS_SUCCESS => 'healthy',
                    default => 'neutral',
                },
                'value' => $latestEvent?->title ?: 'No deploy events yet',
                'detail' => $latestEvent?->occurred_at
                    ? 'Last event at ' . $latestEvent->occurred_at->toDateTimeString()
                    : 'Provision staging, connect a domain or build an export to start the history.',
            ],
        ];

        $overallStatus = 'neutral';

        foreach ($checks as $check) {
            if ($check['status'] === 'critical') {
                $overallStatus = 'critical';
                break;
            }

            if ($check['status'] === 'warning') {
                $overallStatus = 'warning';
            } elseif ($check['status'] === 'healthy' && $overallStatus === 'neutral') {
                $overallStatus = 'healthy';
            }
        }

        return [
            'overall_status' => $overallStatus,
            'checks' => $checks,
            'dns_check' => $dnsCheck ? [
                'domain' => $dnsCheck['domain'] ?? null,
                'matched' => (bool) ($dnsCheck['matched'] ?? false),
                'records' => array_values($dnsCheck['records'] ?? []),
                'expected' => $dnsCheck['expected'] ?? null,
            ] : null,
        ];
    }
}
