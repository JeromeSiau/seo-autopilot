<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Resources\KeywordResource;
use App\Http\Resources\SiteResource;
use App\Jobs\DiscoverKeywordsJob;
use App\Jobs\GenerateArticleJob;
use App\Models\Keyword;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KeywordController extends Controller
{
    public function index(Request $request): Response
    {
        $team = $request->user()->currentTeam;
        $siteIds = $team->sites()->pluck('id');

        $baseQuery = Keyword::whereIn('site_id', $siteIds);

        // Stats
        $stats = [
            'total' => (clone $baseQuery)->count(),
            'queued' => (clone $baseQuery)->where('status', 'queued')->count(),
            'generating' => (clone $baseQuery)->where('status', 'generating')->count(),
            'completed' => (clone $baseQuery)->where('status', 'completed')->count(),
        ];

        $query = Keyword::whereIn('site_id', $siteIds)
            ->with(['site', 'article']);

        // Filters
        if ($request->filled('site_id')) {
            $query->where('site_id', $request->site_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $query->where('keyword', 'like', '%' . $request->search . '%');
        }

        $keywords = $query->orderByDesc('priority')
            ->orderByDesc('score')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Keywords/Index', [
            'keywords' => KeywordResource::collection($keywords)->response()->getData(true),
            'sites' => SiteResource::collection($team->sites()->get())->resolve(),
            'filters' => $request->only(['site_id', 'status', 'search']),
            'stats' => $stats,
        ]);
    }

    public function create(Request $request): Response
    {
        $team = $request->user()->currentTeam;

        return Inertia::render('Keywords/Create', [
            'sites' => SiteResource::collection($team->sites()->get())->resolve(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'site_id' => ['required', 'exists:sites,id'],
            'keyword' => ['required', 'string', 'max:255'],
        ]);

        $team = $request->user()->currentTeam;

        // Verify site belongs to team
        $site = $team->sites()->findOrFail($validated['site_id']);

        $site->keywords()->create([
            'keyword' => $validated['keyword'],
            'source' => 'manual',
            'status' => 'pending',
        ]);

        return redirect()->route('keywords.index')
            ->with('success', 'Keyword added successfully.');
    }

    public function generate(Keyword $keyword): RedirectResponse
    {
        $this->authorize('update', $keyword);

        if ($keyword->status !== 'pending') {
            return back()->with('error', 'Keyword is not pending.');
        }

        $keyword->update(['status' => 'processing']);

        GenerateArticleJob::dispatch($keyword);

        return back()->with('success', 'Article generation started.');
    }

    public function generateBulk(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'keyword_ids' => ['required', 'array'],
            'keyword_ids.*' => ['exists:keywords,id'],
        ]);

        $team = $request->user()->currentTeam;
        $siteIds = $team->sites()->pluck('id');

        $keywords = Keyword::whereIn('id', $validated['keyword_ids'])
            ->whereIn('site_id', $siteIds)
            ->where('status', 'pending')
            ->get();

        foreach ($keywords as $keyword) {
            $keyword->update(['status' => 'processing']);
            GenerateArticleJob::dispatch($keyword);
        }

        return back()->with('success', "Started generation for {$keywords->count()} keywords.");
    }

    public function discover(Request $request): RedirectResponse
    {
        $team = $request->user()->currentTeam;

        if ($request->filled('site_id')) {
            $site = $team->sites()->findOrFail($request->site_id);
            DiscoverKeywordsJob::dispatch($site);

            return back()->with('success', "Keyword discovery started for {$site->domain}.");
        }

        $sites = $team->sites()->get();
        foreach ($sites as $site) {
            DiscoverKeywordsJob::dispatch($site);
        }

        return back()->with('success', "Keyword discovery started for {$sites->count()} sites.");
    }

    public function destroy(Keyword $keyword): RedirectResponse
    {
        $this->authorize('delete', $keyword);

        $keyword->delete();

        return back()->with('success', 'Keyword deleted successfully.');
    }
}
