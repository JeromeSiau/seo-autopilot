<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Site;
use App\Services\Analytics\AnalyticsSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function __construct(
        private readonly AnalyticsSyncService $analyticsService,
    ) {}

    public function dashboard(Request $request, Site $site): JsonResponse
    {
        $this->authorize('view', $site);

        $days = $request->integer('days', 30);
        $summary = $this->analyticsService->getDashboardSummary($site, $days);

        return response()->json([
            'data' => $summary,
        ]);
    }

    public function article(Request $request, Article $article): JsonResponse
    {
        $this->authorize('view', $article->site);

        $days = $request->integer('days', 30);

        $analytics = $article->analytics()
            ->where('date', '>=', now()->subDays($days)->format('Y-m-d'))
            ->orderBy('date')
            ->get();

        return response()->json([
            'data' => [
                'article' => [
                    'id' => $article->id,
                    'title' => $article->title,
                    'published_url' => $article->published_url,
                    'published_at' => $article->published_at,
                ],
                'totals' => [
                    'impressions' => $article->total_impressions,
                    'clicks' => $article->total_clicks,
                    'avg_position' => $article->average_position,
                    'estimated_value' => $article->estimated_value,
                    'roi' => $article->roi,
                ],
                'daily' => $analytics,
            ],
        ]);
    }

    public function sync(Request $request, Site $site): JsonResponse
    {
        $this->authorize('update', $site);

        $days = $request->integer('days', 7);

        try {
            $stats = $this->analyticsService->syncSite($site, $days);

            return response()->json([
                'message' => 'Analytics sync completed',
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Analytics sync failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
