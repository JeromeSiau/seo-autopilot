<?php

namespace App\Services\Analytics;

use App\Models\Article;
use App\Models\ArticleAnalytic;
use App\Models\Site;
use App\Services\Google\GA4Service;
use App\Services\Google\SearchConsoleService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class AnalyticsSyncService
{
    public function __construct(
        private readonly SearchConsoleService $searchConsole,
        private readonly GA4Service $ga4,
    ) {}

    /**
     * Sync analytics data for a site.
     */
    public function syncSite(Site $site, int $days = 7): array
    {
        Log::info("Starting analytics sync for site: {$site->domain}");

        $stats = [
            'articles_synced' => 0,
            'gsc_records' => 0,
            'ga4_records' => 0,
            'errors' => [],
        ];

        $articles = $site->articles()
            ->where('status', 'published')
            ->whereNotNull('published_url')
            ->get();

        foreach ($articles as $article) {
            try {
                $synced = $this->syncArticle($article, $days);

                if ($synced) {
                    $stats['articles_synced']++;
                    $stats['gsc_records'] += $synced['gsc'];
                    $stats['ga4_records'] += $synced['ga4'];
                }
            } catch (\Exception $e) {
                $stats['errors'][] = [
                    'article_id' => $article->id,
                    'error' => $e->getMessage(),
                ];
                Log::warning("Analytics sync failed for article {$article->id}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info("Analytics sync completed for site: {$site->domain}", $stats);

        return $stats;
    }

    /**
     * Sync analytics for a single article.
     */
    public function syncArticle(Article $article, int $days = 7): ?array
    {
        $site = $article->site;

        if (!$article->published_url) {
            return null;
        }

        $pagePath = $this->extractPagePath($article->published_url);
        $endDate = now()->subDay()->format('Y-m-d');
        $startDate = now()->subDays($days)->format('Y-m-d');

        $stats = ['gsc' => 0, 'ga4' => 0];

        // Sync GSC data
        if ($site->isGscConnected()) {
            $gscData = $this->getGscDataForPage($site, $article->published_url, $startDate, $endDate);
            $stats['gsc'] = $this->saveGscData($article, $gscData);
        }

        // Sync GA4 data
        if ($site->isGa4Connected()) {
            $ga4Data = $this->getGa4DataForPage($site, $pagePath, $days);
            $stats['ga4'] = $this->saveGa4Data($article, $ga4Data);
        }

        return $stats;
    }

    /**
     * Get GSC data for a specific page.
     */
    private function getGscDataForPage(
        Site $site,
        string $pageUrl,
        string $startDate,
        string $endDate,
    ): Collection {
        return $this->searchConsole->getSearchAnalytics(
            $site,
            $startDate,
            $endDate,
            ['date'],
            1000,
            [
                [
                    'dimension' => 'page',
                    'operator' => 'equals',
                    'expression' => $pageUrl,
                ],
            ]
        );
    }

    /**
     * Get GA4 data for a specific page.
     */
    private function getGa4DataForPage(Site $site, string $pagePath, int $days): Collection
    {
        return $this->ga4->getPageData($site, $pagePath, $days);
    }

    /**
     * Save GSC data to database.
     */
    private function saveGscData(Article $article, Collection $data): int
    {
        $saved = 0;

        foreach ($data as $row) {
            // Skip if we don't have a date
            if (!isset($row->date)) {
                continue;
            }

            $date = Carbon::parse($row->date)->format('Y-m-d');

            ArticleAnalytic::updateOrCreate(
                [
                    'article_id' => $article->id,
                    'date' => $date,
                ],
                [
                    'impressions' => $row->impressions,
                    'clicks' => $row->clicks,
                    'position' => round($row->position, 1),
                    'ctr' => round($row->ctr * 100, 2),
                ]
            );

            $saved++;
        }

        return $saved;
    }

    /**
     * Save GA4 data to database.
     */
    private function saveGa4Data(Article $article, Collection $data): int
    {
        $saved = 0;

        foreach ($data as $row) {
            if (!isset($row['date'])) {
                continue;
            }

            $date = $row['date'];

            ArticleAnalytic::updateOrCreate(
                [
                    'article_id' => $article->id,
                    'date' => $date,
                ],
                [
                    'sessions' => $row['sessions'] ?? 0,
                    'page_views' => $row['pageviews'] ?? 0,
                    'avg_time_on_page' => $row['avg_duration'] ?? 0,
                    'bounce_rate' => $row['bounce_rate'] ?? 0,
                ]
            );

            $saved++;
        }

        return $saved;
    }

    /**
     * Extract page path from URL.
     */
    private function extractPagePath(string $url): string
    {
        return parse_url($url, PHP_URL_PATH) ?? '/';
    }

    /**
     * Get dashboard summary for a site.
     */
    public function getDashboardSummary(Site $site, int $days = 30): array
    {
        $startDate = now()->subDays($days)->format('Y-m-d');

        $totals = ArticleAnalytic::whereHas('article', fn($q) => $q->where('site_id', $site->id))
            ->where('date', '>=', $startDate)
            ->selectRaw('
                SUM(impressions) as total_impressions,
                SUM(clicks) as total_clicks,
                AVG(position) as avg_position,
                AVG(ctr) as avg_ctr,
                SUM(sessions) as total_sessions,
                SUM(pageviews) as total_pageviews
            ')
            ->first();

        // Get daily trend
        $dailyTrend = ArticleAnalytic::whereHas('article', fn($q) => $q->where('site_id', $site->id))
            ->where('date', '>=', $startDate)
            ->selectRaw('date, SUM(clicks) as clicks, SUM(impressions) as impressions')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Get top performing articles
        $topArticles = Article::where('site_id', $site->id)
            ->where('status', 'published')
            ->withSum(['analytics as total_clicks' => fn($q) => $q->where('date', '>=', $startDate)], 'clicks')
            ->orderByDesc('total_clicks')
            ->limit(10)
            ->get();

        // Get articles needing attention (position dropped)
        $needsAttention = $this->getArticlesNeedingAttention($site, $days);

        return [
            'totals' => [
                'impressions' => (int) ($totals->total_impressions ?? 0),
                'clicks' => (int) ($totals->total_clicks ?? 0),
                'avg_position' => round($totals->avg_position ?? 0, 1),
                'avg_ctr' => round($totals->avg_ctr ?? 0, 2),
                'sessions' => (int) ($totals->total_sessions ?? 0),
                'pageviews' => (int) ($totals->total_pageviews ?? 0),
            ],
            'daily_trend' => $dailyTrend,
            'top_articles' => $topArticles,
            'needs_attention' => $needsAttention,
        ];
    }

    /**
     * Get articles with declining performance.
     */
    private function getArticlesNeedingAttention(Site $site, int $days = 30): Collection
    {
        $midpoint = now()->subDays($days / 2)->format('Y-m-d');
        $startDate = now()->subDays($days)->format('Y-m-d');

        // Get articles with position drop
        $articles = Article::where('site_id', $site->id)
            ->where('status', 'published')
            ->get();

        return $articles->filter(function ($article) use ($midpoint, $startDate) {
            $recentAvg = $article->analytics()
                ->where('date', '>=', $midpoint)
                ->avg('position');

            $previousAvg = $article->analytics()
                ->whereBetween('date', [$startDate, $midpoint])
                ->avg('position');

            if (!$recentAvg || !$previousAvg) {
                return false;
            }

            // Flag if position dropped by more than 5
            return ($recentAvg - $previousAvg) > 5;
        })->map(function ($article) use ($midpoint, $startDate) {
            $recentAvg = $article->analytics()->where('date', '>=', $midpoint)->avg('position');
            $previousAvg = $article->analytics()->whereBetween('date', [$startDate, $midpoint])->avg('position');

            return [
                'article' => $article,
                'position_change' => round($recentAvg - $previousAvg, 1),
                'current_position' => round($recentAvg, 1),
            ];
        })->sortByDesc('position_change')->values();
    }
}
