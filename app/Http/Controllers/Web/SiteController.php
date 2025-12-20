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
            'site' => (new SiteResource($site))->resolve(),
        ]);
    }

    public function edit(Site $site): Response
    {
        $this->authorize('update', $site);

        return Inertia::render('Sites/Edit', [
            'site' => (new SiteResource($site))->resolve(),
        ]);
    }

    public function update(Request $request, Site $site): RedirectResponse
    {
        $this->authorize('update', $site);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'language' => ['required', 'string', 'size:2'],
        ]);

        $site->update($validated);

        return redirect()->route('sites.index')
            ->with('success', 'Site updated successfully.');
    }

    public function destroy(Site $site): RedirectResponse
    {
        $this->authorize('delete', $site);

        $site->delete();

        return redirect()->route('sites.index')
            ->with('success', 'Site deleted successfully.');
    }
}
