<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Resources\BrandAssetResource;
use App\Http\Resources\BrandRuleResource;
use App\Http\Resources\SiteResource;
use App\Jobs\RefreshSiteArticleScoresJob;
use App\Models\BrandAsset;
use App\Models\BrandRule;
use App\Models\Site;
use App\Services\Brand\BrandAssetIngestionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BrandKitController extends Controller
{
    public function show(Site $site): Response
    {
        $this->authorize('view', $site);

        $site->load([
            'hosting',
            'hostedPages',
            'articles',
            'brandAssets' => fn ($query) => $query->orderByDesc('priority')->latest(),
            'brandRules' => fn ($query) => $query->orderByDesc('priority')->latest(),
        ]);

        return Inertia::render('Sites/BrandKit', [
            'site' => (new SiteResource($site))->toArray(request()),
            'brandAssets' => BrandAssetResource::collection($site->brandAssets)->resolve(),
            'brandRules' => BrandRuleResource::collection($site->brandRules)->resolve(),
            'assetTypes' => BrandAsset::TYPES,
            'ruleCategories' => BrandRule::CATEGORIES,
            'contentImportSummary' => [
                'available_pages' => $site->hostedPages
                    ->where('is_published', true)
                    ->filter(fn ($page) => filled(strip_tags($page->body_html ?? '')))
                    ->count(),
                'imported_page_assets' => $site->brandAssets
                    ->filter(fn ($asset) => data_get($asset->metadata, 'imported_from') === 'hosted_page')
                    ->count(),
                'available_articles' => $site->articles
                    ->where('status', 'published')
                    ->filter(fn ($article) => filled(strip_tags($article->content ?? '')))
                    ->count(),
                'imported_article_assets' => $site->brandAssets
                    ->filter(fn ($asset) => data_get($asset->metadata, 'imported_from') === 'published_article')
                    ->count(),
            ],
        ]);
    }

    public function importHostedPages(Site $site, BrandAssetIngestionService $ingestionService): RedirectResponse
    {
        $this->authorize('update', $site);
        abort_unless($site->isHosted(), 404);

        $result = $ingestionService->importHostedPages($site);

        $message = $result['created'] === 0 && $result['updated'] === 0
            ? 'No hosted pages were eligible for import.'
            : "Hosted pages synced: {$result['created']} created, {$result['updated']} updated, {$result['skipped']} skipped.";

        $this->dispatchScoreRefresh($site);

        return back()->with('success', $message);
    }

    public function importPublishedArticles(Site $site, BrandAssetIngestionService $ingestionService): RedirectResponse
    {
        $this->authorize('update', $site);

        $result = $ingestionService->importPublishedArticles($site);

        $message = $result['created'] === 0 && $result['updated'] === 0
            ? 'No published articles were eligible for import.'
            : "Published articles synced: {$result['created']} created, {$result['updated']} updated, {$result['skipped']} skipped.";

        $this->dispatchScoreRefresh($site);

        return back()->with('success', $message);
    }

    public function storeAsset(Request $request, Site $site): RedirectResponse
    {
        $this->authorize('update', $site);

        $validated = $request->validate([
            'type' => ['required', Rule::in(BrandAsset::TYPES)],
            'title' => ['required', 'string', 'max:255'],
            'source_url' => ['nullable', 'url', 'max:2048'],
            'content' => ['required', 'string', 'max:10000'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $site->brandAssets()->create([
            ...$validated,
            'priority' => $validated['priority'] ?? 50,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        $this->dispatchScoreRefresh($site);

        return back()->with('success', 'Brand asset added.');
    }

    public function updateAsset(Request $request, Site $site, BrandAsset $brandAsset): RedirectResponse
    {
        $this->authorize('update', $site);
        abort_unless($brandAsset->site_id === $site->id, 404);

        $validated = $request->validate([
            'type' => ['required', Rule::in(BrandAsset::TYPES)],
            'title' => ['required', 'string', 'max:255'],
            'source_url' => ['nullable', 'url', 'max:2048'],
            'content' => ['required', 'string', 'max:10000'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:100'],
            'is_active' => ['required', 'boolean'],
        ]);

        $brandAsset->update([
            ...$validated,
            'priority' => $validated['priority'] ?? 50,
        ]);

        $this->dispatchScoreRefresh($site);

        return back()->with('success', 'Brand asset updated.');
    }

    public function destroyAsset(Site $site, BrandAsset $brandAsset): RedirectResponse
    {
        $this->authorize('update', $site);
        abort_unless($brandAsset->site_id === $site->id, 404);

        $brandAsset->delete();

        $this->dispatchScoreRefresh($site);

        return back()->with('success', 'Brand asset deleted.');
    }

    public function storeRule(Request $request, Site $site): RedirectResponse
    {
        $this->authorize('update', $site);

        $validated = $request->validate([
            'category' => ['required', Rule::in(BrandRule::CATEGORIES)],
            'label' => ['required', 'string', 'max:255'],
            'value' => ['required', 'string', 'max:5000'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $site->brandRules()->create([
            ...$validated,
            'priority' => $validated['priority'] ?? 50,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        $this->dispatchScoreRefresh($site);

        return back()->with('success', 'Brand rule added.');
    }

    public function updateRule(Request $request, Site $site, BrandRule $brandRule): RedirectResponse
    {
        $this->authorize('update', $site);
        abort_unless($brandRule->site_id === $site->id, 404);

        $validated = $request->validate([
            'category' => ['required', Rule::in(BrandRule::CATEGORIES)],
            'label' => ['required', 'string', 'max:255'],
            'value' => ['required', 'string', 'max:5000'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:100'],
            'is_active' => ['required', 'boolean'],
        ]);

        $brandRule->update([
            ...$validated,
            'priority' => $validated['priority'] ?? 50,
        ]);

        $this->dispatchScoreRefresh($site);

        return back()->with('success', 'Brand rule updated.');
    }

    public function destroyRule(Site $site, BrandRule $brandRule): RedirectResponse
    {
        $this->authorize('update', $site);
        abort_unless($brandRule->site_id === $site->id, 404);

        $brandRule->delete();

        $this->dispatchScoreRefresh($site);

        return back()->with('success', 'Brand rule deleted.');
    }

    protected function dispatchScoreRefresh(Site $site): void
    {
        RefreshSiteArticleScoresJob::dispatch($site);
    }
}
