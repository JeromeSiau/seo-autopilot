<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\DetectRefreshCandidatesJob;
use App\Jobs\GenerateAiPromptSetJob;
use App\Jobs\RunAiVisibilityChecksJob;
use App\Models\Article;
use App\Models\Site;
use App\Services\AiVisibility\AiVisibilityAlertService;
use App\Services\AiVisibility\AiVisibilityRecommendationService;
use App\Services\AiVisibility\AiVisibilityScoringService;
use App\Services\Analytics\AnalyticsSyncService;
use App\Services\Analytics\BusinessAttributionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function __construct(
        private readonly AnalyticsSyncService $analyticsService,
        private readonly AiVisibilityScoringService $aiVisibilityScoring,
        private readonly AiVisibilityRecommendationService $aiVisibilityRecommendations,
        private readonly AiVisibilityAlertService $aiVisibilityAlerts,
        private readonly BusinessAttributionService $businessAttribution,
    ) {}

    public function dashboard(Request $request, Site $site): JsonResponse
    {
        $this->authorize('view', $site);

        $days = $request->integer('days', 30);
        $summary = $this->analyticsService->getDashboardSummary($site, $days);

        return response()->json([
            'data' => $summary + [
                'business_summary' => $this->businessAttribution->summarizeSites($site, $days),
            ],
        ]);
    }

    public function aiVisibility(Request $request, Site $site): JsonResponse
    {
        $this->authorize('view', $site);

        $latestChecks = $this->aiVisibilityScoring->latestChecksForSites([$site->id]);
        $payload = $this->aiVisibilityScoring->buildDashboardPayload(collect([$site]));
        $payload['recommendations'] = $this->aiVisibilityRecommendations->buildRecommendations(
            $site->loadMissing(['articles.keyword', 'brandAssets']),
            $latestChecks,
        );
        $this->aiVisibilityAlerts->syncForSite($site, $payload['alerts'] ?? [], $latestChecks);
        $payload = $this->aiVisibilityScoring->buildDashboardPayload(collect([$site]));
        $payload['recommendations'] = $this->aiVisibilityRecommendations->buildRecommendations(
            $site->loadMissing(['articles.keyword', 'brandAssets']),
            $this->aiVisibilityScoring->latestChecksForSites([$site->id]),
        );

        return response()->json([
            'data' => $payload,
        ]);
    }

    public function refreshRecommendations(Request $request, Site $site): JsonResponse
    {
        $this->authorize('view', $site);

        return response()->json([
            'data' => $site->refreshRecommendations()
                ->with('article')
                ->latest('detected_at')
                ->get()
                ->map(fn ($recommendation) => [
                    'id' => $recommendation->id,
                    'article_id' => $recommendation->article_id,
                    'article_title' => $recommendation->article?->title,
                    'trigger_type' => $recommendation->trigger_type,
                    'severity' => $recommendation->severity,
                    'reason' => $recommendation->reason,
                    'recommended_actions' => $recommendation->recommended_actions ?? [],
                    'metrics_snapshot' => $recommendation->metrics_snapshot ?? [],
                    'status' => $recommendation->status,
                    'detected_at' => optional($recommendation->detected_at)->toIso8601String(),
                    'executed_at' => optional($recommendation->executed_at)->toIso8601String(),
                ])
                ->values(),
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
                    'sessions' => $article->total_sessions,
                    'page_views' => $article->total_page_views,
                    'conversions' => $article->total_conversions,
                    'estimated_conversions' => $article->estimated_conversions,
                    'conversion_source' => $article->conversion_source,
                    'conversion_rate' => $article->conversion_rate,
                    'avg_position' => $article->average_position,
                    'estimated_value' => $article->estimated_value,
                    'roi' => $article->roi,
                ],
                'business_attribution' => $this->businessAttribution->summarizeArticle($article, $days),
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

    public function syncAiVisibility(Request $request, Site $site): JsonResponse
    {
        $this->authorize('update', $site);

        $provider = $request->string('provider')->toString() ?: null;

        GenerateAiPromptSetJob::dispatch($site);
        RunAiVisibilityChecksJob::dispatch($site, null, $provider);

        return response()->json([
            'message' => 'AI visibility sync started',
        ]);
    }

    public function detectRefresh(Request $request, Site $site): JsonResponse
    {
        $this->authorize('update', $site);

        DetectRefreshCandidatesJob::dispatch($site);

        return response()->json([
            'message' => 'Refresh detection started',
        ]);
    }
}
