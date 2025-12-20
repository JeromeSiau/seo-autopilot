<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\IntegrationResource;
use App\Models\Integration;
use App\Services\Publisher\PublisherManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class IntegrationController extends Controller
{
    public function __construct(
        private readonly PublisherManager $publisherManager,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $integrations = $request->user()->team->integrations()
            ->with('site')
            ->latest()
            ->paginate(20);

        return IntegrationResource::collection($integrations);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:wordpress,webflow,shopify',
            'name' => 'required|string|max:255',
            'site_id' => 'nullable|exists:sites,id',
            'credentials' => 'required|array',
        ]);

        // Validate credentials
        $errors = $this->publisherManager->validateCredentials(
            $validated['type'],
            $validated['credentials']
        );

        if (!empty($errors)) {
            return response()->json([
                'message' => 'Invalid credentials',
                'errors' => $errors,
            ], 422);
        }

        $integration = $request->user()->team->integrations()->create([
            'type' => $validated['type'],
            'name' => $validated['name'],
            'site_id' => $validated['site_id'],
            'credentials' => $validated['credentials'],
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Integration created successfully',
            'data' => new IntegrationResource($integration),
        ], 201);
    }

    public function update(Request $request, Integration $integration): JsonResponse
    {
        if ($integration->team_id !== $request->user()->team_id) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'site_id' => 'nullable|exists:sites,id',
            'credentials' => 'sometimes|array',
            'is_active' => 'sometimes|boolean',
        ]);

        if (isset($validated['credentials'])) {
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
        if ($integration->team_id !== $request->user()->team_id) {
            abort(403);
        }

        $integration->delete();

        return response()->json([
            'message' => 'Integration deleted successfully',
        ]);
    }

    public function test(Request $request, Integration $integration): JsonResponse
    {
        if ($integration->team_id !== $request->user()->team_id) {
            abort(403);
        }

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
        if ($integration->team_id !== $request->user()->team_id) {
            abort(403);
        }

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
}
