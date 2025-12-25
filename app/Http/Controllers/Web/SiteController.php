<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Resources\SiteResource;
use App\Models\Site;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SiteController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $team = $user->currentTeam;

        if (!$team) {
            $team = $user->createTeam($user->name . "'s Team");
        }

        $sites = $team->sites()
            ->withCount(['keywords', 'articles'])
            ->latest()
            ->paginate(12);

        return Inertia::render('Sites/Index', [
            'sites' => SiteResource::collection($sites)->response()->getData(true),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Sites/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'domain' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'language' => ['required', 'string', 'size:2'],
        ]);

        // Clean domain
        $domain = preg_replace('#^https?://#', '', $validated['domain']);
        $domain = preg_replace('#^www\.#', '', $domain);
        $domain = rtrim($domain, '/');

        $request->user()->currentTeam->sites()->create([
            'domain' => $domain,
            'name' => $validated['name'],
            'language' => $validated['language'],
        ]);

        return redirect()->route('sites.index')
            ->with('success', 'Site added successfully.');
    }

    public function show(Site $site): Response
    {
        $this->authorize('view', $site);

        $site->loadCount(['keywords', 'articles', 'integrations']);
        $site->load([
            'keywords' => fn($q) => $q->orderByDesc('score')->limit(10),
            'settings',
        ]);

        return Inertia::render('Sites/Show', [
            'site' => (new SiteResource($site))->response()->getData(true)['data'],
        ]);
    }

    public function edit(Site $site): Response
    {
        $this->authorize('update', $site);

        return Inertia::render('Sites/Edit', [
            'site' => (new SiteResource($site))->response()->getData(true)['data'],
        ]);
    }

    public function update(Request $request, Site $site): RedirectResponse
    {
        $this->authorize('update', $site);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'language' => ['required', 'string', 'size:2'],
            'business_description' => ['nullable', 'string', 'max:2000'],
            'target_audience' => ['nullable', 'string', 'max:500'],
            'tone' => ['nullable', 'string', 'in:professional,casual,expert,friendly,neutral'],
            'writing_style' => ['nullable', 'string', 'max:1000'],
            'vocabulary' => ['nullable', 'array'],
            'vocabulary.use' => ['nullable', 'array'],
            'vocabulary.use.*' => ['string', 'max:100'],
            'vocabulary.avoid' => ['nullable', 'array'],
            'vocabulary.avoid.*' => ['string', 'max:100'],
            'brand_examples' => ['nullable', 'array', 'max:5'],
            'brand_examples.*' => ['string', 'max:2000'],
        ]);

        $site->update($validated);

        return redirect()->route('sites.show', $site)
            ->with('success', 'Site updated successfully.');
    }

    public function destroy(Site $site): RedirectResponse
    {
        $this->authorize('delete', $site);

        $site->delete();

        return redirect()->route('sites.index')
            ->with('success', 'Site deleted successfully.');
    }

    public function contentPlanPage(Site $site): Response
    {
        $this->authorize('view', $site);

        $stats = [
            'keywords_total' => $site->keywords()->count(),
            'articles_planned' => $site->scheduledArticles()->where('status', 'planned')->count(),
            'articles_generated' => $site->scheduledArticles()->whereIn('status', ['ready', 'generating'])->count(),
            'articles_published' => $site->scheduledArticles()->where('status', 'published')->count(),
        ];

        $lastGeneration = $site->contentPlanGenerations()->latest()->first();
        $canRegenerate = !$lastGeneration || $lastGeneration->status !== 'running';

        return Inertia::render('Sites/ContentPlan', [
            'site' => (new SiteResource($site))->response()->getData(true)['data'],
            'stats' => $stats,
            'canRegenerate' => $canRegenerate,
        ]);
    }

    public function regenerateContentPlan(Site $site): RedirectResponse
    {
        $this->authorize('update', $site);

        // Delete existing generation and schedule new one
        $site->contentPlanGenerations()->delete();
        $site->scheduledArticles()->where('status', 'planned')->delete();

        \App\Jobs\GenerateContentPlanJob::dispatch($site);

        return redirect()->route('onboarding.generating', ['site' => $site->id]);
    }
}
