<?php

namespace App\Services\Google;

use App\Models\Site;
use App\Services\Google\DTOs\GoogleTokens;
use App\Services\Google\DTOs\SearchAnalyticsRow;
use App\Services\Google\DTOs\SiteInfo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SearchConsoleService
{
    private const BASE_URL = 'https://www.googleapis.com/webmasters/v3';
    private const SEARCHANALYTICS_URL = 'https://searchconsole.googleapis.com/webmasters/v3';

    public function __construct(
        private readonly GoogleAuthService $authService,
    ) {}

    /**
     * List all verified sites for the authenticated user.
     */
    public function listSites(GoogleTokens $tokens): Collection
    {
        $response = $this->request($tokens, 'GET', '/sites');

        $sites = $response['siteEntry'] ?? [];

        return collect($sites)->map(fn($site) => SiteInfo::fromApi($site));
    }

    /**
     * Get search analytics data.
     */
    public function getSearchAnalytics(
        Site $site,
        string $startDate,
        string $endDate,
        array $dimensions = ['query', 'page'],
        int $rowLimit = 1000,
        ?array $filters = null,
    ): Collection {
        $tokens = $this->authService->getValidTokensForSite($site);

        if (!$tokens) {
            throw new \RuntimeException('Site not connected to Google Search Console');
        }

        $siteUrl = $this->getSiteUrl($site);

        $body = [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'dimensions' => $dimensions,
            'rowLimit' => $rowLimit,
        ];

        if ($filters) {
            $body['dimensionFilterGroups'] = [
                ['filters' => $filters],
            ];
        }

        $response = $this->request(
            $tokens,
            'POST',
            "/sites/" . urlencode($siteUrl) . "/searchAnalytics/query",
            $body
        );

        $rows = $response['rows'] ?? [];

        return collect($rows)->map(
            fn($row) => SearchAnalyticsRow::fromApiRow($row, $dimensions)
        );
    }

    /**
     * Get quick-win keywords (positions 5-30 with good impressions).
     */
    public function getQuickWinKeywords(
        Site $site,
        int $days = 28,
        int $minImpressions = 50,
    ): Collection {
        $endDate = now()->subDay()->format('Y-m-d');
        $startDate = now()->subDays($days)->format('Y-m-d');

        $data = $this->getSearchAnalytics($site, $startDate, $endDate, ['query'], 5000);

        return $data
            ->filter(fn($row) => $row->isQuickWin() && $row->impressions >= $minImpressions)
            ->sortByDesc('impressions')
            ->values();
    }

    /**
     * Get opportunity keywords (high impressions, low CTR).
     */
    public function getOpportunityKeywords(
        Site $site,
        int $days = 28,
        int $minImpressions = 100,
        float $maxCtr = 0.02,
    ): Collection {
        $endDate = now()->subDay()->format('Y-m-d');
        $startDate = now()->subDays($days)->format('Y-m-d');

        $data = $this->getSearchAnalytics($site, $startDate, $endDate, ['query'], 5000);

        return $data
            ->filter(fn($row) => $row->isOpportunity($minImpressions, $maxCtr))
            ->sortByDesc('impressions')
            ->values();
    }

    /**
     * Get page performance data.
     */
    public function getPagePerformance(
        Site $site,
        string $pageUrl,
        int $days = 28,
    ): Collection {
        $endDate = now()->subDay()->format('Y-m-d');
        $startDate = now()->subDays($days)->format('Y-m-d');

        return $this->getSearchAnalytics(
            $site,
            $startDate,
            $endDate,
            ['query', 'date'],
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
     * Get daily performance for a site.
     */
    public function getDailyPerformance(
        Site $site,
        int $days = 28,
    ): Collection {
        $endDate = now()->subDay()->format('Y-m-d');
        $startDate = now()->subDays($days)->format('Y-m-d');

        $tokens = $this->authService->getValidTokensForSite($site);

        if (!$tokens) {
            throw new \RuntimeException('Site not connected to Google Search Console');
        }

        $siteUrl = $this->getSiteUrl($site);

        $response = $this->request(
            $tokens,
            'POST',
            "/sites/" . urlencode($siteUrl) . "/searchAnalytics/query",
            [
                'startDate' => $startDate,
                'endDate' => $endDate,
                'dimensions' => ['date'],
                'rowLimit' => 1000,
            ]
        );

        $rows = $response['rows'] ?? [];

        return collect($rows)->map(fn($row) => [
            'date' => $row['keys'][0] ?? null,
            'clicks' => $row['clicks'] ?? 0,
            'impressions' => $row['impressions'] ?? 0,
            'ctr' => round(($row['ctr'] ?? 0) * 100, 2),
            'position' => round($row['position'] ?? 0, 1),
        ]);
    }

    /**
     * Make an API request.
     */
    private function request(
        GoogleTokens $tokens,
        string $method,
        string $endpoint,
        ?array $body = null,
    ): array {
        $url = self::SEARCHANALYTICS_URL . $endpoint;

        $request = Http::withToken($tokens->accessToken)
            ->timeout(30);

        $response = match (strtoupper($method)) {
            'GET' => $request->get($url),
            'POST' => $request->post($url, $body),
            default => throw new \InvalidArgumentException("Unsupported method: {$method}"),
        };

        if (!$response->successful()) {
            Log::error('Search Console API error', [
                'status' => $response->status(),
                'body' => $response->body(),
                'endpoint' => $endpoint,
            ]);
            throw new \RuntimeException('Search Console API error: ' . $response->body());
        }

        return $response->json() ?? [];
    }

    /**
     * Get the Search Console site URL format for a site.
     * Uses gsc_property_id if set, otherwise falls back to sc-domain format.
     */
    private function getSiteUrl(Site $site): string
    {
        // Use saved property ID if available
        if ($site->gsc_property_id) {
            return $site->gsc_property_id;
        }

        // Fallback to domain property format
        $domain = $site->domain;
        $domain = preg_replace('#^https?://#', '', $domain);
        $domain = rtrim($domain, '/');

        return 'sc-domain:' . $domain;
    }
}
