<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Resources\IntegrationResource;
use App\Http\Resources\SiteResource;
use App\Models\Integration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $team = $user->currentTeam;

        if (!$team) {
            $team = $user->createTeam($user->name . "'s Team");
        }

        $siteIds = $team->sites()->pluck('id');

        $integrations = Integration::whereIn('site_id', $siteIds)
            ->with('site')
            ->latest()
            ->get();

        return Inertia::render('Integrations/Index', [
            'integrations' => IntegrationResource::collection($integrations)->resolve(),
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $request->user();
        $team = $user->currentTeam;

        if (!$team) {
            $team = $user->createTeam($user->name . "'s Team");
        }

        return Inertia::render('Integrations/Create', [
            'sites' => SiteResource::collection($team->sites()->get())->resolve(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'site_id' => ['required', 'exists:sites,id'],
            'type' => ['required', 'in:wordpress,webflow,shopify'],
            'name' => ['required', 'string', 'max:255'],
            'credentials' => ['required', 'array'],
        ]);

        $team = $request->user()->currentTeam;

        // Verify site belongs to team
        $site = $team->sites()->findOrFail($validated['site_id']);

        $site->integrations()->create([
            'type' => $validated['type'],
            'name' => $validated['name'],
            'credentials' => Crypt::encryptString(json_encode($validated['credentials'])),
            'is_active' => true,
        ]);

        return redirect()->route('integrations.index')
            ->with('success', 'Integration added successfully.');
    }

    public function edit(Integration $integration): Response
    {
        $this->authorize('update', $integration);

        return Inertia::render('Integrations/Edit', [
            'integration' => new IntegrationResource($integration),
        ]);
    }

    public function update(Request $request, Integration $integration): RedirectResponse
    {
        $this->authorize('update', $integration);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'credentials' => ['nullable', 'array'],
        ]);

        $updateData = ['name' => $validated['name']];

        if (!empty($validated['credentials'])) {
            $updateData['credentials'] = Crypt::encryptString(json_encode($validated['credentials']));
        }

        $integration->update($updateData);

        return redirect()->route('integrations.index')
            ->with('success', 'Integration updated successfully.');
    }

    public function toggle(Integration $integration): RedirectResponse
    {
        $this->authorize('update', $integration);

        $integration->update(['is_active' => !$integration->is_active]);

        return back()->with('success', 'Integration ' . ($integration->is_active ? 'activated' : 'deactivated') . '.');
    }

    public function destroy(Integration $integration): RedirectResponse
    {
        $this->authorize('delete', $integration);

        $integration->delete();

        return redirect()->route('integrations.index')
            ->with('success', 'Integration deleted successfully.');
    }
}
