<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\IntegrationResource;
use App\Models\Integration;
use App\Services\Publisher\PublisherManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class IntegrationController extends Controller
{
    public function __construct(
        private readonly PublisherManager $publisherManager,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $integrations = $request->user()->currentTeam->integrations()
            ->with('site')
            ->latest()
            ->paginate(20);

        return IntegrationResource::collection($integrations);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in($this->publisherManager->getSupportedTypes())],
            'name' => 'required|string|max:255',
            'site_id' => 'required|exists:sites,id',
            'credentials' => 'required|array',
        ]);

        $team = $request->user()->currentTeam;
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

        // Validate credentials
        $errors = $this->publisherManager->validateCredentials(
            $validated['type'],
            $credentials
        );

        if (!empty($errors)) {
            return response()->json([
                'message' => 'Invalid credentials',
                'errors' => $errors,
            ], 422);
        }

        $integration = $team->integrations()->updateOrCreate(
            ['site_id' => $site->id],
            [
                'type' => $validated['type'],
                'name' => $validated['name'],
                'credentials' => $credentials,
                'is_active' => true,
            ]
        );

        return response()->json([
            'message' => 'Integration created successfully',
            'data' => new IntegrationResource($integration),
        ], 201);
    }

    public function update(Request $request, Integration $integration): JsonResponse
    {
        $this->authorize('update', $integration);
        abort_if($integration->isHosted(), 404);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'credentials' => 'sometimes|array',
            'is_active' => 'sometimes|boolean',
        ]);

        if (isset($validated['credentials'])) {
            $validated['credentials'] = $this->publisherManager->mergeCredentials(
                $integration->type,
                $this->resolveCredentials($integration->credentials),
                $validated['credentials'],
            );
            $errors = $this->publisherManager->validateCredentials(
                $integration->type,
                $validated['credentials']
            );

            if (!empty($errors)) {
                return response()->json([
                    'message' => 'Invalid credentials',
                    'errors' => $errors,
                ], 422);
            }
        }

        $integration->update($validated);

        return response()->json([
            'message' => 'Integration updated successfully',
            'data' => new IntegrationResource($integration),
        ]);
    }

    public function destroy(Request $request, Integration $integration): JsonResponse
    {
        $this->authorize('delete', $integration);
        abort_if($integration->isHosted(), 404);

        $integration->delete();

        return response()->json([
            'message' => 'Integration deleted successfully',
        ]);
    }

    public function test(Request $request, Integration $integration): JsonResponse
    {
        $this->authorize('view', $integration);

        try {
            $publisher = $this->publisherManager->getPublisher($integration);
            $success = $publisher->testConnection();

            return response()->json([
                'success' => $success,
                'message' => $success ? 'Connection successful' : 'Connection failed',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function categories(Request $request, Integration $integration): JsonResponse
    {
        $this->authorize('view', $integration);

        try {
            $publisher = $this->publisherManager->getPublisher($integration);
            $categories = $publisher->getCategories();

            return response()->json([
                'data' => $categories,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'data' => [],
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function resolveCredentials(array|string $credentials): array
    {
        if (is_array($credentials)) {
            return $credentials;
        }

        return [];
    }
}
