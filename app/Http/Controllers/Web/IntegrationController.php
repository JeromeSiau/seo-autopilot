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

        // Update or create - only one integration per site allowed
        Integration::updateOrCreate(
            ['site_id' => $site->id],
            [
                'team_id' => $team->id,
                'type' => $validated['type'],
                'name' => $validated['name'],
                'credentials' => $validated['credentials'],
                'is_active' => true,
            ]
        );

        // Redirect back to wizard if onboarding not completed
        if (!$site->onboarding_completed_at) {
            return redirect()->route('onboarding.resume', $site)
                ->with('success', 'Intégration ajoutée avec succès !');
        }

        return redirect()->route('integrations.index')
            ->with('success', 'Integration added successfully.');
    }

    public function edit(Integration $integration): Response
    {
        $this->authorize('update', $integration);

        $integration->load('site');

        // Get credentials - handle legacy double-encrypted data
        $credentials = $integration->credentials ?? [];

        // If credentials is a string, it might be double-encrypted (legacy data)
        if (is_string($credentials)) {
            try {
                $decrypted = Crypt::decryptString($credentials);
                $credentials = json_decode($decrypted, true) ?? [];
            } catch (\Exception $e) {
                $credentials = [];
            }
        }

        // Remove password fields for security
        $safeCredentials = collect($credentials)->except(['password', 'api_token'])->toArray();

        // Build integration data with properly resolved site
        $integrationData = [
            'id' => $integration->id,
            'type' => $integration->type,
            'name' => $integration->name,
            'is_active' => $integration->is_active,
            'site_id' => $integration->site_id,
            'site' => $integration->site ? [
                'id' => $integration->site->id,
                'name' => $integration->site->name,
                'domain' => $integration->site->domain,
            ] : null,
            'credentials' => $safeCredentials,
            'created_at' => $integration->created_at,
        ];

        return Inertia::render('Integrations/Edit', [
            'integration' => $integrationData,
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
            // Get existing credentials - handle legacy double-encrypted data
            $existingCredentials = $integration->credentials ?? [];

            if (is_string($existingCredentials)) {
                try {
                    $decrypted = Crypt::decryptString($existingCredentials);
                    $existingCredentials = json_decode($decrypted, true) ?? [];
                } catch (\Exception $e) {
                    $existingCredentials = [];
                }
            }

            // Merge new credentials with existing ones (to preserve passwords if not changed)
            $newCredentials = array_filter($validated['credentials'], fn($value) => $value !== '' && $value !== null);
            $updateData['credentials'] = array_merge($existingCredentials, $newCredentials);
        }

        $integration->update($updateData);

        return redirect()->route('integrations.index')
            ->with('success', 'Intégration mise à jour avec succès.');
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
