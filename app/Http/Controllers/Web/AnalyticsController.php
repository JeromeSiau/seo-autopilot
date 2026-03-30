<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Resources\SiteResource;
use App\Jobs\DetectRefreshCandidatesJob;
use App\Jobs\GenerateAiPromptSetJob;
use App\Jobs\RunAiVisibilityChecksJob;
use App\Jobs\SyncSiteAnalyticsJob;
use App\Models\Site;
use App\Models\SiteAnalytic;
use App\Services\AiVisibility\AiVisibilityAlertService;
use App\Services\AiVisibility\AiVisibilityRecommendationService;
use App\Services\AiVisibility\AiVisibilityScoringService;
use App\Services\Analytics\BusinessAttributionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsController extends Controller
{
    public function __construct(
        private readonly AiVisibilityScoringService $aiVisibilityScoring,
        private readonly AiVisibilityRecommendationService $aiVisibilityRecommendations,
        private readonly AiVisibilityAlertService $aiVisibilityAlerts,
        private readonly BusinessAttributionService $businessAttribution,
    ) {}

    public function index(Request $request): Response
    {
        $team = $request->user()->currentTeam;
        $sites = $team->sites()->get();
        $connectedSites = $sites->filter(fn ($site) => $site->isGscConnected());

        $selectedSite = null;
        $analyticsData = collect();
        $summary = $this->getEmptySummary();
        $topPages = [];
        $topQueries = [];
        $aiVisibility = [
            'summary' => [
                'total_prompts' => 0,
                'checked_prompts' => 0,
                'covered_prompts' => 0,
                'avg_visibility_score' => 0,
                'avg_visibility_delta' => 0,
                'declining_checks' => 0,
                'improving_checks' => 0,
                'high_risk_prompts' => 0,
                'last_checked_at' => null,
            ],
            'engines' => [],
            'top_prompts' => [],
            'weakest_prompts' => [],
            'trend' => [],
            'alerts' => [],
            'movers' => [],
            'competitors' => [],
            'sources' => [],
            'intents' => [],
            'recommendations' => [],
            'prompt_sets' => [],
            'alert_history' => [],
        ];
        $refreshRecommendations = [];
        $dateRange = $request->get('range', '28');
        $days = (int) $dateRange;
        $businessSummary = $this->businessAttribution->summarizeSites(collect(), $days);
        $startDate = now()->subDays($days);

        if ($request->filled('site_id')) {
            // Explicit site selection
            $selectedSite = $sites->find($request->site_id);
        } elseif ($sites->count() === 1) {
            // Auto-select if only one site
            $selectedSite = $sites->first();
        }

        if ($selectedSite && $selectedSite->isGscConnected()) {
            // Single site view
            $analyticsData = $this->getAnalyticsData([$selectedSite->id], $startDate);
            $summary = $this->calculateSummary([$selectedSite->id], $startDate, $days);
            // TODO: Get top pages and queries from GSC API
        } elseif (!$selectedSite && $connectedSites->isNotEmpty()) {
            // Aggregated view for all connected sites
            $connectedSiteIds = $connectedSites->pluck('id')->toArray();
            $analyticsData = $this->getAnalyticsData($connectedSiteIds, $startDate);
            $summary = $this->calculateSummary($connectedSiteIds, $startDate, $days);
        }

        if ($selectedSite) {
            $aiVisibility = $this->buildSiteAiVisibilityPayload($selectedSite);
            $refreshRecommendations = $this->refreshRecommendationsForSite($selectedSite);
            $businessSummary = $this->businessAttribution->summarizeSites($selectedSite, $days);
        } elseif ($sites->isNotEmpty()) {
            $aiVisibility = $this->aiVisibilityScoring->buildDashboardPayload($sites);
            $businessSummary = $this->businessAttribution->summarizeSites($sites, $days);
        }

        return Inertia::render('Analytics/Index', [
            'sites' => SiteResource::collection($sites)->resolve(),
            'selectedSite' => $selectedSite ? (new SiteResource($selectedSite))->resolve() : null,
            'analyticsData' => $analyticsData,
            'summary' => $summary,
            'topPages' => $topPages,
            'topQueries' => $topQueries,
            'dateRange' => $dateRange,
            'connectedSitesCount' => $connectedSites->count(),
            'totalSitesCount' => $sites->count(),
            'aiVisibility' => $aiVisibility,
            'refreshRecommendations' => $refreshRecommendations,
            'businessSummary' => $businessSummary,
        ]);
    }

    public function aiVisibility(Request $request): Response
    {
        $team = $request->user()->currentTeam;
        $sites = $team->sites()->get();
        $selectedSite = $request->filled('site_id')
            ? $sites->find($request->integer('site_id'))
            : ($sites->count() === 1 ? $sites->first() : null);

        $aiVisibility = $selectedSite
            ? $this->buildSiteAiVisibilityPayload($selectedSite)
            : [
                'summary' => [
                    'total_prompts' => 0,
                    'checked_prompts' => 0,
                    'covered_prompts' => 0,
                    'avg_visibility_score' => 0,
                    'avg_visibility_delta' => 0,
                    'declining_checks' => 0,
                    'improving_checks' => 0,
                    'high_risk_prompts' => 0,
                    'last_checked_at' => null,
                ],
                'engines' => [],
                'top_prompts' => [],
                'weakest_prompts' => [],
                'trend' => [],
                'alerts' => [],
                'movers' => [],
                'competitors' => [],
                'sources' => [],
                'intents' => [],
                'recommendations' => [],
                'prompt_sets' => [],
                'alert_history' => [],
            ];

        return Inertia::render('Analytics/AiVisibility', [
            'sites' => SiteResource::collection($sites)->resolve(),
            'selectedSite' => $selectedSite ? (new SiteResource($selectedSite))->resolve() : null,
            'aiVisibility' => $aiVisibility,
            'refreshRecommendations' => $selectedSite ? $this->refreshRecommendationsForSite($selectedSite) : [],
        ]);
    }

    private function getAnalyticsData(array $siteIds, $startDate): \Illuminate\Support\Collection
    {
        return SiteAnalytic::whereIn('site_id', $siteIds)
            ->where('date', '>=', $startDate)
            ->selectRaw('date, SUM(clicks) as clicks, SUM(impressions) as impressions, SUM(position * impressions) / NULLIF(SUM(impressions), 0) as position')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->date->format('Y-m-d'),
                'clicks' => (int) $row->clicks,
                'impressions' => (int) $row->impressions,
                'position' => round($row->position ?? 0, 1),
            ]);
    }

    private function calculateSummary(array $siteIds, $startDate, int $days): array
    {
        $currentPeriod = SiteAnalytic::whereIn('site_id', $siteIds)
            ->where('date', '>=', $startDate)
            ->selectRaw('SUM(clicks) as total_clicks, SUM(impressions) as total_impressions, SUM(position * impressions) / NULLIF(SUM(impressions), 0) as avg_position')
            ->first();

        $previousPeriod = SiteAnalytic::whereIn('site_id', $siteIds)
            ->whereBetween('date', [$startDate->copy()->subDays($days), $startDate])
            ->selectRaw('SUM(clicks) as total_clicks, SUM(impressions) as total_impressions')
            ->first();

        $totalClicks = $currentPeriod->total_clicks ?? 0;
        $totalImpressions = $currentPeriod->total_impressions ?? 0;
        $avgCtr = $totalImpressions > 0 ? ($totalClicks / $totalImpressions) * 100 : 0;

        $prevClicks = $previousPeriod->total_clicks ?? 0;
        $prevImpressions = $previousPeriod->total_impressions ?? 0;

        $clicksChange = $prevClicks > 0
            ? round((($totalClicks - $prevClicks) / $prevClicks) * 100, 1)
            : 0;

        $impressionsChange = $prevImpressions > 0
            ? round((($totalImpressions - $prevImpressions) / $prevImpressions) * 100, 1)
            : 0;

        return [
            'total_clicks' => (int) $totalClicks,
            'total_impressions' => (int) $totalImpressions,
            'avg_position' => round($currentPeriod->avg_position ?? 0, 1),
            'avg_ctr' => round($avgCtr, 2),
            'clicks_change' => $clicksChange,
            'impressions_change' => $impressionsChange,
        ];
    }

    public function sync(Site $site): RedirectResponse
    {
        $this->authorize('update', $site);

        SyncSiteAnalyticsJob::dispatch($site, 28);

        return back()->with('success', 'Analytics sync started.');
    }

    public function syncAiVisibility(Site $site): RedirectResponse
    {
        $this->authorize('update', $site);

        GenerateAiPromptSetJob::dispatch($site);
        RunAiVisibilityChecksJob::dispatch($site);

        return back()->with('success', 'AI visibility sync started.');
    }

    public function detectRefresh(Site $site): RedirectResponse
    {
        $this->authorize('update', $site);

        DetectRefreshCandidatesJob::dispatch($site);

        return back()->with('success', 'Refresh detection started.');
    }

    private function getEmptySummary(): array
    {
        return [
            'total_clicks' => 0,
            'total_impressions' => 0,
            'avg_position' => 0,
            'avg_ctr' => 0,
            'clicks_change' => 0,
            'impressions_change' => 0,
        ];
    }

    private function buildSiteAiVisibilityPayload(Site $site): array
    {
        $payload = $this->aiVisibilityScoring->buildDashboardPayload(collect([$site]));
        $payload['recommendations'] = $this->aiVisibilityRecommendations->buildRecommendations(
            $site->loadMissing(['articles.keyword', 'brandAssets']),
            $this->aiVisibilityScoring->latestChecksForSites([$site->id]),
        );
        $this->aiVisibilityAlerts->syncForSite(
            $site,
            $payload['alerts'] ?? [],
            $this->aiVisibilityScoring->latestChecksForSites([$site->id]),
        );
        $payload = $this->aiVisibilityScoring->buildDashboardPayload(collect([$site]));
        $payload['recommendations'] = $this->aiVisibilityRecommendations->buildRecommendations(
            $site->loadMissing(['articles.keyword', 'brandAssets']),
            $this->aiVisibilityScoring->latestChecksForSites([$site->id]),
        );

        return $payload;
    }

    private function refreshRecommendationsForSite(Site $site): array
    {
        return $site->refreshRecommendations()
            ->with('article')
            ->whereIn('status', ['open', 'accepted', 'executed'])
            ->latest('detected_at')
            ->take(10)
            ->get()
            ->map(fn ($recommendation) => [
                'id' => $recommendation->id,
                'article_id' => $recommendation->article_id,
                'article_title' => $recommendation->article?->title,
                'trigger_type' => $recommendation->trigger_type,
                'severity' => $recommendation->severity,
                'reason' => $recommendation->reason,
                'recommended_actions' => $recommendation->recommended_actions ?? [],
                'status' => $recommendation->status,
                'detected_at' => optional($recommendation->detected_at)->toIso8601String(),
            ])
            ->values()
            ->all();
    }
}
