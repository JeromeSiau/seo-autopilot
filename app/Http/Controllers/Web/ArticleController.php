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
use App\Models\HostedAuthor;
use App\Models\HostedCategory;
use App\Models\HostedTag;
use App\Models\Integration;
use App\Models\Keyword;
use App\Services\Content\ArticleScoringService;
use App\Services\Hosted\HostedSiteService;
use App\Services\Webhooks\WebhookDispatcher;
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

        $baseQuery = Article::whereIn('site_id', $siteIds);

        // Stats
        $stats = [
            'total' => (clone $baseQuery)->count(),
            'review' => (clone $baseQuery)->where('status', 'review')->count(),
            'approved' => (clone $baseQuery)->where('status', 'approved')->count(),
            'published' => (clone $baseQuery)->where('status', 'published')->count(),
        ];

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
            'stats' => $stats,
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
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'keyword_id' => ['required', 'exists:keywords,id'],
            'generate_images' => ['boolean'],
        ]);

        $team = $request->user()->currentTeam;

        // Verify keyword belongs to team
        $keyword = Keyword::whereIn('site_id', $team->sites()->pluck('id'))
            ->findOrFail($validated['keyword_id']);

        if (!$team->canGenerateArticle()) {
            return back()->with('error', 'You have reached your article limit for this month.');
        }

        $keyword->addToQueue();

        GenerateArticleJob::dispatch(
            $keyword,
            $validated['generate_images'] ?? true,
        );

        return redirect()->route('articles.index')
            ->with('success', 'Article generation started.');
    }

    public function show(Request $request, Article $article): Response
    {
        $this->authorize('view', $article);

        $article->load([
            'site.brandAssets',
            'site.brandRules',
            'site.hostedAuthors',
            'site.hostedCategories',
            'site.hostedTags',
            'site.team.users',
            'keyword',
            'analytics',
            'score',
            'citations',
            'agentEvents',
            'hostedAuthor',
            'hostedCategory',
            'hostedTags',
            'editorialComments.user',
            'assignments.user',
            'approvalRequests.requestedBy',
            'approvalRequests.requestedTo',
            'refreshRecommendations',
            'refreshRuns',
        ]);

        if (!$article->score) {
            app(ArticleScoringService::class)->scoreAndSave($article);
            $article->load('score');
        }

        $integrations = Integration::where('site_id', $article->site_id)
            ->where('is_active', true)
            ->get();

        return Inertia::render('Articles/Show', [
            'article' => (new ArticleResource($article))->resolve(),
            'integrations' => IntegrationResource::collection($integrations)->resolve(),
            'teamMembers' => $article->site->team->users
                ->map(fn ($member) => [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'role' => $member->id === $article->site->team->owner_id ? 'owner' : $member->pivot->role,
                    'joined_at' => $member->pivot->created_at,
                ])
                ->values()
                ->all(),
            'currentUserRole' => $request->user()->roleForTeam($article->site->team),
        ]);
    }

    public function edit(Article $article): Response
    {
        $this->authorize('update', $article);

        $article->load(['site', 'keyword']);

        return Inertia::render('Articles/Edit', [
            'article' => (new ArticleResource($article))->resolve(),
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
        app(ArticleScoringService::class)->scoreAndSave($article->fresh([
            'site.brandAssets',
            'site.brandRules',
            'keyword',
            'citations',
            'agentEvents',
        ]));

        return redirect()->route('articles.show', $article)
            ->with('success', 'Article updated successfully.');
    }

    public function updateHostedMetadata(Request $request, Article $article): RedirectResponse
    {
        $this->authorize('update', $article);
        abort_unless($article->site->isHosted(), 404);

        $validated = $request->validate([
            'hosted_author_id' => ['nullable', 'integer'],
            'hosted_category_id' => ['nullable', 'integer'],
            'hosted_tag_ids' => ['nullable', 'array'],
            'hosted_tag_ids.*' => ['integer'],
        ]);

        $authorId = $validated['hosted_author_id'] ?? null;
        $categoryId = $validated['hosted_category_id'] ?? null;
        $tagIds = collect($validated['hosted_tag_ids'] ?? [])->filter()->map(fn ($value) => (int) $value)->unique()->values();

        if ($authorId !== null) {
            HostedAuthor::query()
                ->where('site_id', $article->site_id)
                ->findOrFail($authorId);
        }

        if ($categoryId !== null) {
            HostedCategory::query()
                ->where('site_id', $article->site_id)
                ->findOrFail($categoryId);
        }

        $ownedTagIds = HostedTag::query()
            ->where('site_id', $article->site_id)
            ->whereIn('id', $tagIds)
            ->pluck('id');

        if ($ownedTagIds->count() !== $tagIds->count()) {
            abort(404);
        }

        $article->update([
            'hosted_author_id' => $authorId,
            'hosted_category_id' => $categoryId,
        ]);
        $article->hostedTags()->sync($tagIds->all());

        if ($article->site->isHosted()) {
            app(HostedSiteService::class)->syncStaticPages($article->site->fresh([
                'hosting',
                'hostedPages',
                'hostedAuthors',
                'hostedCategories',
                'hostedTags',
            ]));
        }

        return back()->with('success', 'Hosted metadata updated.');
    }

    public function approve(Article $article, WebhookDispatcher $webhooks): RedirectResponse
    {
        $this->authorize('approve', $article);

        if (!in_array($article->status, [Article::STATUS_DRAFT, Article::STATUS_REVIEW], true)) {
            return back()->with('error', 'Only draft or review articles can be approved.');
        }

        $article->update(['status' => Article::STATUS_APPROVED]);

        $webhooks->dispatch($article->site->team, 'article.approved', [
            'team_id' => $article->site->team_id,
            'site_id' => $article->site_id,
            'article_id' => $article->id,
            'title' => $article->title,
            'status' => $article->status,
        ]);

        return back()->with('success', 'Article approved for publishing.');
    }

    public function publish(Request $request, Article $article): RedirectResponse
    {
        $this->authorize('publish', $article);

        if (!$article->isApproved()) {
            return back()->with('error', 'Only approved articles can be published.');
        }

        $validated = $request->validate([
            'integration_id' => ['required', 'exists:integrations,id'],
        ]);

        $integration = Integration::where('site_id', $article->site_id)
            ->where('is_active', true)
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
