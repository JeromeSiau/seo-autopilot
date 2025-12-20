<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SiteResource;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SiteController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $sites = $request->user()->team->sites()
            ->withCount(['articles', 'keywords'])
            ->latest()
            ->paginate(20);

        return SiteResource::collection($sites);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'domain' => 'required|string|max:255',
            'language' => 'required|string|max:5',
        ]);

        $site = $request->user()->team->sites()->create($validated);

        return response()->json([
            'message' => 'Site created successfully',
            'data' => new SiteResource($site),
        ], 201);
    }

    public function show(Request $request, Site $site): SiteResource
    {
        $this->authorize('view', $site);

        $site->loadCount(['articles', 'keywords']);

        return new SiteResource($site);
    }

    public function update(Request $request, Site $site): JsonResponse
    {
        $this->authorize('update', $site);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'domain' => 'sometimes|string|max:255',
            'language' => 'sometimes|string|max:5',
            'ga4_property_id' => 'sometimes|nullable|string',
        ]);

        $site->update($validated);

        return response()->json([
            'message' => 'Site updated successfully',
            'data' => new SiteResource($site),
        ]);
    }

    public function destroy(Request $request, Site $site): JsonResponse
    {
        $this->authorize('delete', $site);

        $site->delete();

        return response()->json([
            'message' => 'Site deleted successfully',
        ]);
    }
}
