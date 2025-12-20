<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\KeywordResource;
use App\Jobs\ClusterKeywordsJob;
use App\Jobs\DiscoverKeywordsJob;
use App\Models\Keyword;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class KeywordController extends Controller
{
    public function index(Request $request, Site $site): AnonymousResourceCollection
    {
        $this->authorize('view', $site);

        $query = $site->keywords()->with('article');

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->cluster_id) {
            $query->where('cluster_id', $request->cluster_id);
        }

        if ($request->search) {
            $query->where('keyword', 'like', "%{$request->search}%");
        }

        $sortBy = $request->sort_by ?? 'score';
        $sortDir = $request->sort_dir ?? 'desc';

        $keywords = $query->orderBy($sortBy, $sortDir)->paginate(50);

        return KeywordResource::collection($keywords);
    }

    public function store(Request $request, Site $site): JsonResponse
    {
        $this->authorize('update', $site);

        $validated = $request->validate([
            'keyword' => 'required|string|max:255',
            'volume' => 'nullable|integer',
            'difficulty' => 'nullable|numeric|min:0|max:100',
        ]);

        $keyword = $site->keywords()->create([
            ...$validated,
            'source' => 'manual',
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Keyword added successfully',
            'data' => new KeywordResource($keyword),
        ], 201);
    }

    public function destroy(Request $request, Site $site, Keyword $keyword): JsonResponse
    {
        $this->authorize('update', $site);

        if ($keyword->site_id !== $site->id) {
            abort(404);
        }

        $keyword->delete();

        return response()->json([
            'message' => 'Keyword deleted successfully',
        ]);
    }

    public function discover(Request $request, Site $site): JsonResponse
    {
        $this->authorize('update', $site);

        $options = $request->validate([
            'use_ai' => 'boolean',
            'enrich' => 'boolean',
            'business_description' => 'nullable|string|max:1000',
        ]);

        DiscoverKeywordsJob::dispatch($site, $options);

        return response()->json([
            'message' => 'Keyword discovery started',
        ]);
    }

    public function cluster(Request $request, Site $site): JsonResponse
    {
        $this->authorize('update', $site);

        ClusterKeywordsJob::dispatch($site);

        return response()->json([
            'message' => 'Keyword clustering started',
        ]);
    }
}
