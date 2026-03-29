<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Resources\IntegrationResource;
use App\Http\Resources\SiteResource;
use App\Models\Integration;
use App\Services\Publisher\PublisherManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationController extends Controller
{
    public function __construct(
        private readonly PublisherManager $publisherManager,
    ) {}

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
            'type' => ['required', Rule::in($this->publisherManager->getSupportedTypes())],
            'name' => ['required', 'string', 'max:255'],
            'credentials' => ['required', 'array'],
        ]);

        $team = $request->user()->currentTeam;

        // Verify site belongs to team
        $site = $team->sites()->findOrFail($validated['site_id']);

        if ($site->isHosted()) {
            throw ValidationException::withMessages([
                'site_id' => 'Hosted blogs manage publishing automatically and cannot add CMS integrations.',
            ]);
        }

        if ($validated['type'] === 'hosted') {
            throw ValidationException::withMessages([
                'type' => 'Hosted integrations are provisioned automatically.',
            ]);
        }

        $credentials = $this->publisherManager->normalizeCredentials($validated['type'], $validated['credentials']);
        $errors = $this->publisherManager->validateCredentials($validated['type'], $credentials);

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        // Update or create - only one integration per site allowed
        Integration::updateOrCreate(
            ['site_id' => $site->id],
            [
                'team_id' => $team->id,
                'type' => $validated['type'],
                'name' => $validated['name'],
                'credentials' => $credentials,
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

        abort_if($integration->isHosted(), 404);

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
        $safeCredentials = $this->publisherManager->getEditableCredentials($integration->type, $credentials);
        $secretFields = $this->publisherManager->getCredentialPresence($integration->type, $credentials);

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
            'secret_fields' => $secretFields,
            'created_at' => $integration->created_at,
        ];

        return Inertia::render('Integrations/Edit', [
            'integration' => $integrationData,
        ]);
    }

    public function update(Request $request, Integration $integration): RedirectResponse
    {
        $this->authorize('update', $integration);
        abort_if($integration->isHosted(), 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'credentials' => ['nullable', 'array'],
        ]);

        $updateData = ['name' => $validated['name']];

        if (array_key_exists('credentials', $validated)) {
            $existingCredentials = $this->resolveCredentials($integration->credentials ?? []);
            $mergedCredentials = $this->publisherManager->mergeCredentials(
                $integration->type,
                $existingCredentials,
                $validated['credentials'] ?? [],
            );
            $errors = $this->publisherManager->validateCredentials($integration->type, $mergedCredentials);

            if (!empty($errors)) {
                throw ValidationException::withMessages($errors);
            }

            $updateData['credentials'] = $mergedCredentials;
        }

        $integration->update($updateData);

        return redirect()->route('integrations.index')
            ->with('success', 'Intégration mise à jour avec succès.');
    }

    public function toggle(Integration $integration): RedirectResponse
    {
        $this->authorize('update', $integration);
        abort_if($integration->isHosted(), 404);

        $integration->update(['is_active' => !$integration->is_active]);

        return back()->with('success', 'Integration ' . ($integration->is_active ? 'activated' : 'deactivated') . '.');
    }

    public function destroy(Integration $integration): RedirectResponse
    {
        $this->authorize('delete', $integration);
        abort_if($integration->isHosted(), 404);

        $integration->delete();

        return redirect()->route('integrations.index')
            ->with('success', 'Integration deleted successfully.');
    }

    private function resolveCredentials(array|string $credentials): array
    {
        if (is_string($credentials)) {
            try {
                $decrypted = Crypt::decryptString($credentials);
                return json_decode($decrypted, true) ?? [];
            } catch (\Exception $e) {
                return [];
            }
        }

        return $credentials;
    }
}
