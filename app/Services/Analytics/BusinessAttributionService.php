<?php

namespace App\Services\Analytics;

use App\Models\Article;
use App\Models\ArticleAnalytic;
use App\Models\Site;
use App\Models\SiteAnalytic;
use App\Models\SiteSetting;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class BusinessAttributionService
{
    public function summarizeArticle(Article $article, int $days = 30): array
    {
        $analytics = $this->analyticsForArticle($article);
        $site = $article->relationLoaded('site') ? $article->site : $article->site()->with('settings')->first();
        $assumptions = $this->assumptionsForSite($site?->settings);
        $cpc = max((float) ($article->keyword?->cpc ?? 0.5), 0.1);
        $recent = $this->metricsForPeriod($analytics, $cpc, $assumptions, $days);
        $previous = $this->metricsForPeriod($analytics, $cpc, $assumptions, $days, $days);
        $totals = $this->metricsForCollection($analytics, $cpc, $assumptions);
        $generationCost = (float) ($article->generation_cost ?? 0);
        $recentSiteSearch = $site ? $this->siteSearchMetrics([$site->id], $days) : ['clicks' => 0, 'impressions' => 0];
        $previousSiteSearch = $site ? $this->siteSearchMetrics([$site->id], $days, $days) : ['clicks' => 0, 'impressions' => 0];
        $totalSiteSearch = $site ? $this->siteSearchMetrics([$site->id], null) : ['clicks' => 0, 'impressions' => 0];

        return [
            'lookback_days' => $days,
            'business_model' => $assumptions,
            'totals' => $totals,
            'recent' => $recent,
            'previous' => $previous,
            'search_capture' => [
                'recent_click_share' => $this->share($recent['clicks'], $recentSiteSearch['clicks']),
                'previous_click_share' => $this->share($previous['clicks'], $previousSiteSearch['clicks']),
                'total_click_share' => $this->share($totals['clicks'], $totalSiteSearch['clicks']),
            ],
            'deltas' => [
                'clicks' => $this->buildDelta($recent['clicks'], $previous['clicks']),
                'sessions' => $this->buildDelta($recent['sessions'], $previous['sessions']),
                'estimated_conversions' => $this->buildDelta($recent['estimated_conversions'], $previous['estimated_conversions']),
                'traffic_value' => $this->buildDelta($recent['traffic_value'], $previous['traffic_value']),
                'attributed_revenue' => $this->buildDelta($recent['attributed_revenue'], $previous['attributed_revenue']),
                'total_value' => $this->buildDelta($recent['total_value'], $previous['total_value']),
            ],
            'generation_cost' => round($generationCost, 2),
            'roi' => $generationCost > 0 ? round(($totals['total_value'] / $generationCost) * 100, 2) : null,
            'performance_label' => $this->performanceLabel($recent, $previous),
        ];
    }

    public function summarizeSites(Collection|Site $sites, int $days = 30): array
    {
        $siteIds = $sites instanceof Site
            ? [$sites->id]
            : $sites->pluck('id')->filter()->unique()->values()->all();

        if (empty($siteIds)) {
            return $this->emptySiteSummary($days);
        }

        $articles = Article::query()
            ->whereIn('site_id', $siteIds)
            ->where('status', Article::STATUS_PUBLISHED)
            ->with([
                'keyword',
                'site.settings',
                'analytics' => fn ($query) => $query->where('date', '>=', now()->subDays($days * 2 + 1)),
                'refreshRuns' => fn ($query) => $query->latest('created_at'),
            ])
            ->get();

        if ($articles->isEmpty()) {
            return $this->emptySiteSummary($days);
        }

        $articleSummaries = $articles->map(function (Article $article) use ($days) {
            return [
                'article' => $article,
                'summary' => $this->summarizeArticle($article, $days),
            ];
        });
        $businessModels = $articles
            ->map(fn (Article $article) => $this->assumptionsForSite($article->site?->settings))
            ->unique(fn (array $assumptions) => "{$assumptions['modeled_conversion_rate']}|{$assumptions['average_conversion_value']}")
            ->values();
        $recentSiteSearch = $this->siteSearchMetrics($siteIds, $days);
        $previousSiteSearch = $this->siteSearchMetrics($siteIds, $days, $days);

        $totals = [
            'traffic_value' => round((float) $articleSummaries->sum(fn (array $row) => data_get($row, 'summary.totals.traffic_value', 0)), 2),
            'attributed_revenue' => round((float) $articleSummaries->sum(fn (array $row) => data_get($row, 'summary.totals.attributed_revenue', 0)), 2),
            'total_value' => round((float) $articleSummaries->sum(fn (array $row) => data_get($row, 'summary.totals.total_value', 0)), 2),
            'estimated_conversions' => round((float) $articleSummaries->sum(fn (array $row) => data_get($row, 'summary.totals.estimated_conversions', 0)), 1),
            'tracked_conversions' => (int) $articleSummaries->sum(fn (array $row) => data_get($row, 'summary.totals.conversions', 0)),
            'sessions' => (int) $articleSummaries->sum(fn (array $row) => data_get($row, 'summary.totals.sessions', 0)),
            'clicks' => (int) $articleSummaries->sum(fn (array $row) => data_get($row, 'summary.totals.clicks', 0)),
            'generation_cost' => round((float) $articleSummaries->sum(fn (array $row) => data_get($row, 'summary.generation_cost', 0)), 2),
        ];
        $recentTrafficValue = (float) $articleSummaries->sum(fn (array $row) => data_get($row, 'summary.recent.traffic_value', 0));
        $previousTrafficValue = (float) $articleSummaries->sum(fn (array $row) => data_get($row, 'summary.previous.traffic_value', 0));
        $recentAttributedRevenue = (float) $articleSummaries->sum(fn (array $row) => data_get($row, 'summary.recent.attributed_revenue', 0));
        $previousAttributedRevenue = (float) $articleSummaries->sum(fn (array $row) => data_get($row, 'summary.previous.attributed_revenue', 0));
        $recentTotalValue = (float) $articleSummaries->sum(fn (array $row) => data_get($row, 'summary.recent.total_value', 0));
        $previousTotalValue = (float) $articleSummaries->sum(fn (array $row) => data_get($row, 'summary.previous.total_value', 0));

        return [
            'lookback_days' => $days,
            'business_model' => [
                'modeled_conversion_rate' => count($siteIds) === 1 ? $businessModels->first()['modeled_conversion_rate'] : null,
                'average_conversion_value' => count($siteIds) === 1 ? $businessModels->first()['average_conversion_value'] : null,
                'source' => count($siteIds) === 1 ? $businessModels->first()['source'] : 'mixed',
                'sites_with_custom_model' => $businessModels->where('source', 'custom')->count(),
                'sites_count' => count($siteIds),
            ],
            'totals' => $totals + [
                'conversion_source' => $totals['tracked_conversions'] > 0 ? 'tracked' : 'modeled',
                'net_value' => round($totals['total_value'] - $totals['generation_cost'], 2),
                'search_click_share' => $this->share($recentSiteSearch['clicks'] > 0 ? (float) $articleSummaries->sum(fn (array $row) => data_get($row, 'summary.recent.clicks', 0)) : 0, $recentSiteSearch['clicks']),
                'roi' => $totals['generation_cost'] > 0
                    ? round(($totals['total_value'] / $totals['generation_cost']) * 100, 2)
                    : null,
            ],
            'deltas' => [
                'traffic_value' => $this->buildDelta($recentTrafficValue, $previousTrafficValue),
                'attributed_revenue' => $this->buildDelta($recentAttributedRevenue, $previousAttributedRevenue),
                'total_value' => $this->buildDelta($recentTotalValue, $previousTotalValue),
                'clicks' => $this->buildDelta(
                    (float) $articleSummaries->sum(fn (array $row) => data_get($row, 'summary.recent.clicks', 0)),
                    (float) $articleSummaries->sum(fn (array $row) => data_get($row, 'summary.previous.clicks', 0)),
                ),
                'sessions' => $this->buildDelta(
                    (float) $articleSummaries->sum(fn (array $row) => data_get($row, 'summary.recent.sessions', 0)),
                    (float) $articleSummaries->sum(fn (array $row) => data_get($row, 'summary.previous.sessions', 0)),
                ),
                'estimated_conversions' => $this->buildDelta(
                    (float) $articleSummaries->sum(fn (array $row) => data_get($row, 'summary.recent.estimated_conversions', 0)),
                    (float) $articleSummaries->sum(fn (array $row) => data_get($row, 'summary.previous.estimated_conversions', 0)),
                ),
            ],
            'search_capture' => [
                'recent_click_share' => $this->share((float) $articleSummaries->sum(fn (array $row) => data_get($row, 'summary.recent.clicks', 0)), $recentSiteSearch['clicks']),
                'previous_click_share' => $this->share((float) $articleSummaries->sum(fn (array $row) => data_get($row, 'summary.previous.clicks', 0)), $previousSiteSearch['clicks']),
                'tracked_site_clicks' => $recentSiteSearch['clicks'],
            ],
            'top_articles' => $articleSummaries
                ->sortByDesc(fn (array $row) => data_get($row, 'summary.totals.total_value', 0))
                ->take(5)
                ->map(fn (array $row) => [
                    'article_id' => $row['article']->id,
                    'title' => $row['article']->title,
                    'traffic_value' => data_get($row, 'summary.totals.traffic_value'),
                    'attributed_revenue' => data_get($row, 'summary.totals.attributed_revenue'),
                    'total_value' => data_get($row, 'summary.totals.total_value'),
                    'estimated_conversions' => data_get($row, 'summary.totals.estimated_conversions'),
                    'search_click_share' => data_get($row, 'summary.search_capture.total_click_share'),
                    'roi' => data_get($row, 'summary.roi'),
                    'performance_label' => data_get($row, 'summary.performance_label'),
                ])
                ->values()
                ->all(),
            'refresh_winners' => $articleSummaries
                ->filter(function (array $row) {
                    $hasRefresh = $row['article']->refreshRuns->isNotEmpty();
                    $valueDelta = (float) data_get($row, 'summary.deltas.traffic_value.absolute', 0);
                    $conversionDelta = (float) data_get($row, 'summary.deltas.estimated_conversions.absolute', 0);

                    return $hasRefresh && ($valueDelta > 0 || $conversionDelta > 0);
                })
                ->sortByDesc(fn (array $row) => data_get($row, 'summary.deltas.total_value.absolute', 0))
                ->take(5)
                ->map(fn (array $row) => [
                    'article_id' => $row['article']->id,
                    'title' => $row['article']->title,
                    'traffic_value_delta' => data_get($row, 'summary.deltas.traffic_value.absolute'),
                    'attributed_revenue_delta' => data_get($row, 'summary.deltas.attributed_revenue.absolute'),
                    'total_value_delta' => data_get($row, 'summary.deltas.total_value.absolute'),
                    'conversion_delta' => data_get($row, 'summary.deltas.estimated_conversions.absolute'),
                    'latest_refresh_at' => optional($row['article']->refreshRuns->first()?->created_at)->toIso8601String(),
                ])
                ->values()
                ->all(),
        ];
    }

    private function analyticsForArticle(Article $article): Collection
    {
        if ($article->relationLoaded('analytics')) {
            return $article->analytics;
        }

        return $article->analytics()->get();
    }

    private function metricsForPeriod(Collection $analytics, float $cpc, array $assumptions, int $days, int $offsetDays = 0): array
    {
        $end = now()->subDays($offsetDays)->endOfDay();
        $start = now()->subDays($offsetDays + $days)->startOfDay();

        return $this->metricsForCollection(
            $analytics->filter(fn (ArticleAnalytic $analytic) => $this->isWithinRange($analytic->date, $start, $end)),
            $cpc,
            $assumptions,
        );
    }

    private function metricsForCollection(Collection $analytics, float $cpc, array $assumptions): array
    {
        $clicks = (int) $analytics->sum('clicks');
        $sessions = (int) $analytics->sum('sessions');
        $conversions = (int) $analytics->sum('conversions');
        $baseline = $sessions > 0 ? $sessions : $clicks;
        $estimatedConversions = $conversions > 0
            ? (float) $conversions
            : round($baseline * (((float) $assumptions['modeled_conversion_rate']) / 100), 1);
        $attributedRevenue = round($estimatedConversions * (float) $assumptions['average_conversion_value'], 2);
        $trafficValue = round($clicks * $cpc, 2);

        return [
            'clicks' => $clicks,
            'sessions' => $sessions,
            'page_views' => (int) $analytics->sum('page_views'),
            'conversions' => $conversions,
            'estimated_conversions' => $estimatedConversions,
            'conversion_source' => $conversions > 0 ? 'tracked' : 'modeled',
            'traffic_value' => $trafficValue,
            'attributed_revenue' => $attributedRevenue,
            'total_value' => round($trafficValue + $attributedRevenue, 2),
            'conversion_rate' => $baseline > 0 ? round(($estimatedConversions / $baseline) * 100, 2) : null,
        ];
    }

    private function buildDelta(float|int|null $recent, float|int|null $previous): array
    {
        $recent = $recent !== null ? (float) $recent : 0.0;
        $previous = $previous !== null ? (float) $previous : 0.0;

        return [
            'absolute' => round($recent - $previous, 2),
            'percentage' => $previous > 0 ? round((($recent - $previous) / $previous) * 100, 2) : null,
        ];
    }

    private function performanceLabel(array $recent, array $previous): string
    {
        $valueDelta = (float) $this->buildDelta($recent['total_value'], $previous['total_value'])['absolute'];
        $conversionDelta = (float) $this->buildDelta($recent['estimated_conversions'], $previous['estimated_conversions'])['absolute'];

        if ($valueDelta >= 50 || $conversionDelta >= 1) {
            return 'accelerating';
        }

        if ($valueDelta <= -50 || $conversionDelta <= -1) {
            return 'at_risk';
        }

        return 'steady';
    }

    private function assumptionsForSite(?SiteSetting $settings): array
    {
        $defaultRate = round((float) config('business.default_modeled_conversion_rate', 2.0), 2);
        $defaultValue = round((float) config('business.default_average_conversion_value', 150.0), 2);
        $customRate = $settings?->modeled_conversion_rate !== null ? (float) $settings->modeled_conversion_rate : null;
        $customValue = $settings?->average_conversion_value !== null ? (float) $settings->average_conversion_value : null;

        return [
            'modeled_conversion_rate' => round($customRate ?? $defaultRate, 2),
            'average_conversion_value' => round($customValue ?? $defaultValue, 2),
            'source' => ($customRate !== null || $customValue !== null) ? 'custom' : 'default',
        ];
    }

    private function siteSearchMetrics(array $siteIds, ?int $days, int $offsetDays = 0): array
    {
        $query = SiteAnalytic::query()->whereIn('site_id', $siteIds);

        if ($days !== null) {
            $end = now()->subDays($offsetDays)->endOfDay();
            $start = now()->subDays($offsetDays + $days)->startOfDay();
            $query->whereBetween('date', [$start, $end]);
        }

        return [
            'clicks' => (int) $query->sum('clicks'),
            'impressions' => (int) (clone $query)->sum('impressions'),
        ];
    }

    private function share(float|int $part, float|int $whole): ?float
    {
        $whole = (float) $whole;

        if ($whole <= 0) {
            return null;
        }

        return round((((float) $part) / $whole) * 100, 2);
    }

    private function isWithinRange(?CarbonInterface $date, CarbonInterface $start, CarbonInterface $end): bool
    {
        if (!$date) {
            return false;
        }

        return !$date->lt($start) && !$date->gt($end);
    }

    private function emptySiteSummary(int $days): array
    {
        return [
            'lookback_days' => $days,
            'totals' => [
                'traffic_value' => 0,
                'attributed_revenue' => 0,
                'total_value' => 0,
                'estimated_conversions' => 0,
                'tracked_conversions' => 0,
                'sessions' => 0,
                'clicks' => 0,
                'generation_cost' => 0,
                'conversion_source' => 'modeled',
                'net_value' => 0,
                'search_click_share' => null,
                'roi' => null,
            ],
            'deltas' => [
                'traffic_value' => ['absolute' => 0, 'percentage' => null],
                'attributed_revenue' => ['absolute' => 0, 'percentage' => null],
                'total_value' => ['absolute' => 0, 'percentage' => null],
                'clicks' => ['absolute' => 0, 'percentage' => null],
                'sessions' => ['absolute' => 0, 'percentage' => null],
                'estimated_conversions' => ['absolute' => 0, 'percentage' => null],
            ],
            'business_model' => [
                'modeled_conversion_rate' => round((float) config('business.default_modeled_conversion_rate', 2.0), 2),
                'average_conversion_value' => round((float) config('business.default_average_conversion_value', 150.0), 2),
                'source' => 'default',
                'sites_with_custom_model' => 0,
                'sites_count' => 0,
            ],
            'search_capture' => [
                'recent_click_share' => null,
                'previous_click_share' => null,
                'tracked_site_clicks' => 0,
            ],
            'top_articles' => [],
            'refresh_winners' => [],
        ];
    }
}
