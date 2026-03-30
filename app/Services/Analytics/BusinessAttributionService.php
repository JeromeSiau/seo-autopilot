<?php

namespace App\Services\Analytics;

use App\Models\Article;
use App\Models\ArticleAnalytic;
use App\Models\Site;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class BusinessAttributionService
{
    public function summarizeArticle(Article $article, int $days = 30): array
    {
        $analytics = $this->analyticsForArticle($article);
        $cpc = max((float) ($article->keyword?->cpc ?? 0.5), 0.1);
        $recent = $this->metricsForPeriod($analytics, $cpc, $days);
        $previous = $this->metricsForPeriod($analytics, $cpc, $days, $days);
        $totals = $this->metricsForCollection($analytics, $cpc);
        $generationCost = (float) ($article->generation_cost ?? 0);

        return [
            'lookback_days' => $days,
            'totals' => $totals,
            'recent' => $recent,
            'previous' => $previous,
            'deltas' => [
                'clicks' => $this->buildDelta($recent['clicks'], $previous['clicks']),
                'sessions' => $this->buildDelta($recent['sessions'], $previous['sessions']),
                'estimated_conversions' => $this->buildDelta($recent['estimated_conversions'], $previous['estimated_conversions']),
                'traffic_value' => $this->buildDelta($recent['traffic_value'], $previous['traffic_value']),
            ],
            'generation_cost' => round($generationCost, 2),
            'roi' => $generationCost > 0 ? round(($totals['traffic_value'] / $generationCost) * 100, 2) : null,
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

        $totals = [
            'traffic_value' => round((float) $articleSummaries->sum(fn (array $row) => data_get($row, 'summary.totals.traffic_value', 0)), 2),
            'estimated_conversions' => round((float) $articleSummaries->sum(fn (array $row) => data_get($row, 'summary.totals.estimated_conversions', 0)), 1),
            'tracked_conversions' => (int) $articleSummaries->sum(fn (array $row) => data_get($row, 'summary.totals.conversions', 0)),
            'sessions' => (int) $articleSummaries->sum(fn (array $row) => data_get($row, 'summary.totals.sessions', 0)),
            'clicks' => (int) $articleSummaries->sum(fn (array $row) => data_get($row, 'summary.totals.clicks', 0)),
            'generation_cost' => round((float) $articleSummaries->sum(fn (array $row) => data_get($row, 'summary.generation_cost', 0)), 2),
        ];
        $recentTrafficValue = (float) $articleSummaries->sum(fn (array $row) => data_get($row, 'summary.recent.traffic_value', 0));
        $previousTrafficValue = (float) $articleSummaries->sum(fn (array $row) => data_get($row, 'summary.previous.traffic_value', 0));

        return [
            'lookback_days' => $days,
            'totals' => $totals + [
                'conversion_source' => $totals['tracked_conversions'] > 0 ? 'tracked' : 'modeled',
                'roi' => $totals['generation_cost'] > 0
                    ? round(($totals['traffic_value'] / $totals['generation_cost']) * 100, 2)
                    : null,
            ],
            'deltas' => [
                'traffic_value' => $this->buildDelta($recentTrafficValue, $previousTrafficValue),
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
            'top_articles' => $articleSummaries
                ->sortByDesc(fn (array $row) => data_get($row, 'summary.totals.traffic_value', 0))
                ->take(5)
                ->map(fn (array $row) => [
                    'article_id' => $row['article']->id,
                    'title' => $row['article']->title,
                    'traffic_value' => data_get($row, 'summary.totals.traffic_value'),
                    'estimated_conversions' => data_get($row, 'summary.totals.estimated_conversions'),
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
                ->sortByDesc(fn (array $row) => data_get($row, 'summary.deltas.traffic_value.absolute', 0))
                ->take(5)
                ->map(fn (array $row) => [
                    'article_id' => $row['article']->id,
                    'title' => $row['article']->title,
                    'traffic_value_delta' => data_get($row, 'summary.deltas.traffic_value.absolute'),
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

    private function metricsForPeriod(Collection $analytics, float $cpc, int $days, int $offsetDays = 0): array
    {
        $end = now()->subDays($offsetDays)->endOfDay();
        $start = now()->subDays($offsetDays + $days)->startOfDay();

        return $this->metricsForCollection(
            $analytics->filter(fn (ArticleAnalytic $analytic) => $this->isWithinRange($analytic->date, $start, $end)),
            $cpc,
        );
    }

    private function metricsForCollection(Collection $analytics, float $cpc): array
    {
        $clicks = (int) $analytics->sum('clicks');
        $sessions = (int) $analytics->sum('sessions');
        $conversions = (int) $analytics->sum('conversions');
        $baseline = $sessions > 0 ? $sessions : $clicks;
        $estimatedConversions = $conversions > 0 ? (float) $conversions : round($baseline * 0.02, 1);

        return [
            'clicks' => $clicks,
            'sessions' => $sessions,
            'page_views' => (int) $analytics->sum('page_views'),
            'conversions' => $conversions,
            'estimated_conversions' => $estimatedConversions,
            'conversion_source' => $conversions > 0 ? 'tracked' : 'modeled',
            'traffic_value' => round($clicks * $cpc, 2),
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
        $valueDelta = (float) $this->buildDelta($recent['traffic_value'], $previous['traffic_value'])['absolute'];
        $conversionDelta = (float) $this->buildDelta($recent['estimated_conversions'], $previous['estimated_conversions'])['absolute'];

        if ($valueDelta >= 15 || $conversionDelta >= 1) {
            return 'accelerating';
        }

        if ($valueDelta <= -15 || $conversionDelta <= -1) {
            return 'at_risk';
        }

        return 'steady';
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
                'estimated_conversions' => 0,
                'tracked_conversions' => 0,
                'sessions' => 0,
                'clicks' => 0,
                'generation_cost' => 0,
                'conversion_source' => 'modeled',
                'roi' => null,
            ],
            'deltas' => [
                'traffic_value' => ['absolute' => 0, 'percentage' => null],
                'clicks' => ['absolute' => 0, 'percentage' => null],
                'sessions' => ['absolute' => 0, 'percentage' => null],
                'estimated_conversions' => ['absolute' => 0, 'percentage' => null],
            ],
            'top_articles' => [],
            'refresh_winners' => [],
        ];
    }
}
