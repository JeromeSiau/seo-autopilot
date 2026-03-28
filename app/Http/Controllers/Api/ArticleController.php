<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ArticleResource;
use App\Jobs\GenerateArticleJob;
use App\Jobs\PublishArticleJob;
use App\Models\Article;
use App\Models\Integration;
use App\Models\Keyword;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ArticleController extends Controller
{
    public function index(Request $request, Site $site): AnonymousResourceCollection
    {
        $this->authorize('view', $site);

        $query = $site->articles()->with(['keyword']);

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->search) {
            $query->where('title', 'like', "%{$request->search}%");
        }

        $articles = $query->latest()->paginate(20);

        return ArticleResource::collection($articles);
    }

    public function show(Request $request, Article $article): ArticleResource
    {
        $this->authorize('view', $article);

        $article->load(['keyword', 'site']);

        return new ArticleResource($article);
    }

    public function update(Request $request, Article $article): JsonResponse
    {
        $this->authorize('update', $article);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'content' => 'sometimes|string',
            'meta_title' => 'sometimes|string|max:70',
            'meta_description' => 'sometimes|string|max:160',
        ]);

        $article->update($validated);

        return response()->json([
            'message' => 'Article updated successfully',
            'data' => new ArticleResource($article),
        ]);
    }

    public function destroy(Request $request, Article $article): JsonResponse
    {
        $this->authorize('delete', $article);

        $article->delete();

        return response()->json([
            'message' => 'Article deleted successfully',
        ]);
    }

    public function generate(Request $request, Keyword $keyword): JsonResponse
    {
        $this->authorize('update', $keyword->site);

        if ($keyword->status !== 'pending') {
            return response()->json([
                'message' => 'Keyword is not pending',
            ], 422);
        }

        $validated = $request->validate([
            'generate_images' => 'boolean',
        ]);

        $keyword->addToQueue();

        GenerateArticleJob::dispatch(
            $keyword,
            $validated['generate_images'] ?? true,
        );

        return response()->json([
            'message' => 'Article generation started',
        ]);
    }

    public function publish(Request $request, Article $article): JsonResponse
    {
        $this->authorize('publish', $article);

        if (!$article->isApproved()) {
            return response()->json([
                'message' => 'Article must be approved before publishing',
            ], 422);
        }

        $validated = $request->validate([
            'integration_id' => 'required|exists:integrations,id',
            'categories' => 'array',
            'status' => 'in:publish,draft',
        ]);

        $integration = Integration::where('site_id', $article->site_id)
            ->where('is_active', true)
            ->findOrFail($validated['integration_id']);

        PublishArticleJob::dispatch(
            $article,
            $integration,
            [
                'categories' => $validated['categories'] ?? [],
                'status' => $validated['status'] ?? 'publish',
            ]
        );

        return response()->json([
            'message' => 'Article publishing started',
        ]);
    }
}
