<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Resources\ArticleResource;
use App\Http\Resources\IntegrationResource;
use App\Http\Resources\KeywordResource;
use App\Http\Resources\SiteResource;
use App\Jobs\GenerateArticleJob;
use App\Jobs\PublishArticleJob;
use App\Models\Article;
use App\Models\Integration;
use App\Models\Keyword;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ArticleController extends Controller
{
    public function index(Request $request): Response
    {
        $team = $request->user()->currentTeam;
        $siteIds = $team->sites()->pluck('id');

        $query = Article::whereIn('site_id', $siteIds)
            ->with(['site', 'keyword']);

        // Filters
        if ($request->filled('site_id')) {
            $query->where('site_id', $request->site_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }

        $articles = $query->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Articles/Index', [
            'articles' => ArticleResource::collection($articles)->response()->getData(true),
            'sites' => SiteResource::collection($team->sites()->get())->resolve(),
            'filters' => $request->only(['site_id', 'status', 'search']),
        ]);
    }

    public function create(Request $request): Response
    {
        $team = $request->user()->currentTeam;
        $siteIds = $team->sites()->pluck('id');

        $pendingKeywords = Keyword::whereIn('site_id', $siteIds)
            ->where('status', 'pending')
            ->orderByDesc('score')
            ->limit(50)
            ->get();

        return Inertia::render('Articles/Create', [
            'sites' => SiteResource::collection($team->sites()->get())->resolve(),
            'keywords' => KeywordResource::collection($pendingKeywords)->resolve(),
            'brandVoices' => $team->brandVoices()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'keyword_id' => ['required', 'exists:keywords,id'],
            'brand_voice_id' => ['nullable', 'exists:brand_voices,id'],
            'generate_images' => ['boolean'],
        ]);

        $team = $request->user()->currentTeam;

        // Verify keyword belongs to team
        $keyword = Keyword::whereIn('site_id', $team->sites()->pluck('id'))
            ->findOrFail($validated['keyword_id']);

        if (!$team->canGenerateArticle()) {
            return back()->with('error', 'You have reached your article limit for this month.');
        }

        $keyword->update(['status' => 'processing']);

        GenerateArticleJob::dispatch(
            $keyword,
            $validated['brand_voice_id'] ?? null,
            $validated['generate_images'] ?? true,
        );

        return redirect()->route('articles.index')
            ->with('success', 'Article generation started.');
    }

    public function show(Article $article): Response
    {
        $this->authorize('view', $article);

        $article->load(['site', 'keyword']);

        $integrations = Integration::where('site_id', $article->site_id)
            ->where('is_active', true)
            ->get();

        return Inertia::render('Articles/Show', [
            'article' => new ArticleResource($article),
            'integrations' => IntegrationResource::collection($integrations)->resolve(),
        ]);
    }

    public function edit(Article $article): Response
    {
        $this->authorize('update', $article);

        $article->load(['site', 'keyword']);

        return Inertia::render('Articles/Edit', [
            'article' => new ArticleResource($article),
        ]);
    }

    public function update(Request $request, Article $article): RedirectResponse
    {
        $this->authorize('update', $article);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'meta_title' => ['nullable', 'string', 'max:60'],
            'meta_description' => ['nullable', 'string', 'max:160'],
            'status' => ['nullable', 'in:draft,review,approved'],
        ]);

        $article->update($validated);

        return redirect()->route('articles.show', $article)
            ->with('success', 'Article updated successfully.');
    }

    public function approve(Article $article): RedirectResponse
    {
        $this->authorize('update', $article);

        $article->update(['status' => 'approved']);

        return back()->with('success', 'Article approved for publishing.');
    }

    public function publish(Request $request, Article $article): RedirectResponse
    {
        $this->authorize('update', $article);

        $validated = $request->validate([
            'integration_id' => ['required', 'exists:integrations,id'],
        ]);

        $integration = Integration::where('site_id', $article->site_id)
            ->findOrFail($validated['integration_id']);

        PublishArticleJob::dispatch($article, $integration);

        return back()->with('success', 'Article is being published.');
    }

    public function destroy(Article $article): RedirectResponse
    {
        $this->authorize('delete', $article);

        $article->delete();

        return redirect()->route('articles.index')
            ->with('success', 'Article deleted successfully.');
    }
}
