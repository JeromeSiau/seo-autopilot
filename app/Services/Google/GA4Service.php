<?php

namespace App\Services\Google;

use App\Models\Site;
use App\Services\Google\DTOs\GA4Report;
use App\Services\Google\DTOs\GoogleTokens;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GA4Service
{
    private const BASE_URL = 'https://analyticsdata.googleapis.com/v1beta';

    public function __construct(
        private readonly GoogleAuthService $authService,
    ) {}

    /**
     * List available GA4 properties.
     */
    public function listProperties(GoogleTokens $tokens): Collection
    {
        $response = Http::withToken($tokens->accessToken)
            ->get('https://analyticsadmin.googleapis.com/v1beta/accountSummaries');

        if (!$response->successful()) {
            throw new \RuntimeException('Failed to list GA4 properties: ' . $response->body());
        }

        $summaries = $response->json()['accountSummaries'] ?? [];
        $properties = [];

        foreach ($summaries as $account) {
            foreach ($account['propertySummaries'] ?? [] as $property) {
                $properties[] = [
                    'property_id' => str_replace('properties/', '', $property['property']),
                    'display_name' => $property['displayName'],
                    'account_name' => $account['displayName'] ?? '',
                ];
            }
        }

        return collect($properties);
    }

    /**
     * Get page performance data.
     */
    public function getPagePerformance(
        Site $site,
        int $days = 28,
        int $limit = 100,
    ): Collection {
        $tokens = $this->getValidTokensForSite($site);
        $propertyId = $site->ga4_property_id;

        if (!$propertyId) {
            throw new \RuntimeException('GA4 property not configured for this site');
        }

        $endDate = now()->format('Y-m-d');
        $startDate = now()->subDays($days)->format('Y-m-d');

        $response = $this->runReport($tokens, $propertyId, [
            'dateRanges' => [
                ['startDate' => $startDate, 'endDate' => $endDate],
            ],
            'dimensions' => [
                ['name' => 'pagePath'],
            ],
            'metrics' => [
                ['name' => 'sessions'],
                ['name' => 'screenPageViews'],
                ['name' => 'averageSessionDuration'],
                ['name' => 'bounceRate'],
                ['name' => 'conversions'],
            ],
            'limit' => $limit,
            'orderBys' => [
                ['metric' => ['metricName' => 'sessions'], 'desc' => true],
            ],
        ]);

        return $this->parseReportRows($response);
    }

    /**
     * Get daily traffic data.
     */
    public function getDailyTraffic(
        Site $site,
        int $days = 28,
    ): Collection {
        $tokens = $this->getValidTokensForSite($site);
        $propertyId = $site->ga4_property_id;

        if (!$propertyId) {
            throw new \RuntimeException('GA4 property not configured for this site');
        }

        $endDate = now()->format('Y-m-d');
        $startDate = now()->subDays($days)->format('Y-m-d');

        $response = $this->runReport($tokens, $propertyId, [
            'dateRanges' => [
                ['startDate' => $startDate, 'endDate' => $endDate],
            ],
            'dimensions' => [
                ['name' => 'date'],
            ],
            'metrics' => [
                ['name' => 'sessions'],
                ['name' => 'screenPageViews'],
                ['name' => 'averageSessionDuration'],
                ['name' => 'bounceRate'],
                ['name' => 'conversions'],
            ],
            'orderBys' => [
                ['dimension' => ['dimensionName' => 'date'], 'desc' => false],
            ],
        ]);

        return collect($response['rows'] ?? [])->map(function ($row) {
            $date = $row['dimensionValues'][0]['value'] ?? '';
            $metrics = $this->extractMetrics($row);

            return [
                'date' => substr($date, 0, 4) . '-' . substr($date, 4, 2) . '-' . substr($date, 6, 2),
                'sessions' => (int) ($metrics['sessions'] ?? 0),
                'pageviews' => (int) ($metrics['screenPageViews'] ?? 0),
                'avg_duration' => round((float) ($metrics['averageSessionDuration'] ?? 0), 1),
                'bounce_rate' => round((float) ($metrics['bounceRate'] ?? 0) * 100, 2),
                'conversions' => (int) ($metrics['conversions'] ?? 0),
            ];
        });
    }

    /**
     * Get performance for a specific page.
     */
    public function getPageData(
        Site $site,
        string $pagePath,
        int $days = 28,
    ): Collection {
        $tokens = $this->getValidTokensForSite($site);
        $propertyId = $site->ga4_property_id;

        if (!$propertyId) {
            throw new \RuntimeException('GA4 property not configured for this site');
        }

        $endDate = now()->format('Y-m-d');
        $startDate = now()->subDays($days)->format('Y-m-d');

        $response = $this->runReport($tokens, $propertyId, [
            'dateRanges' => [
                ['startDate' => $startDate, 'endDate' => $endDate],
            ],
            'dimensions' => [
                ['name' => 'date'],
            ],
            'metrics' => [
                ['name' => 'sessions'],
                ['name' => 'screenPageViews'],
                ['name' => 'averageSessionDuration'],
                ['name' => 'bounceRate'],
            ],
            'dimensionFilter' => [
                'filter' => [
                    'fieldName' => 'pagePath',
                    'stringFilter' => [
                        'matchType' => 'EXACT',
                        'value' => $pagePath,
                    ],
                ],
            ],
            'orderBys' => [
                ['dimension' => ['dimensionName' => 'date'], 'desc' => false],
            ],
        ]);

        return collect($response['rows'] ?? [])->map(function ($row) {
            $date = $row['dimensionValues'][0]['value'] ?? '';
            $metrics = $this->extractMetrics($row);

            return [
                'date' => substr($date, 0, 4) . '-' . substr($date, 4, 2) . '-' . substr($date, 6, 2),
                'sessions' => (int) ($metrics['sessions'] ?? 0),
                'pageviews' => (int) ($metrics['screenPageViews'] ?? 0),
                'avg_duration' => round((float) ($metrics['averageSessionDuration'] ?? 0), 1),
                'bounce_rate' => round((float) ($metrics['bounceRate'] ?? 0) * 100, 2),
            ];
        });
    }

    /**
     * Run a GA4 report.
     */
    private function runReport(GoogleTokens $tokens, string $propertyId, array $body): array
    {
        $url = self::BASE_URL . "/properties/{$propertyId}:runReport";

        $response = Http::withToken($tokens->accessToken)
            ->timeout(30)
            ->post($url, $body);

        if (!$response->successful()) {
            Log::error('GA4 API error', [
                'status' => $response->status(),
                'body' => $response->body(),
                'property_id' => $propertyId,
            ]);
            throw new \RuntimeException('GA4 API error: ' . $response->body());
        }

        return $response->json() ?? [];
    }

    /**
     * Parse report rows into DTOs.
     */
    private function parseReportRows(array $response): Collection
    {
        $dimensionHeaders = collect($response['dimensionHeaders'] ?? [])
            ->pluck('name')
            ->toArray();

        $metricHeaders = collect($response['metricHeaders'] ?? [])
            ->pluck('name')
            ->toArray();

        return collect($response['rows'] ?? [])->map(function ($row) use ($dimensionHeaders, $metricHeaders) {
            $dimensions = [];
            foreach ($dimensionHeaders as $i => $name) {
                $dimensions[$name] = $row['dimensionValues'][$i]['value'] ?? null;
            }

            $metrics = $this->extractMetrics($row);

            return GA4Report::fromApiRow($dimensions, $metrics);
        });
    }

    /**
     * Extract metrics from a row.
     */
    private function extractMetrics(array $row): array
    {
        $metricNames = ['sessions', 'screenPageViews', 'averageSessionDuration', 'bounceRate', 'conversions'];
        $metrics = [];

        foreach ($row['metricValues'] ?? [] as $i => $value) {
            if (isset($metricNames[$i])) {
                $metrics[$metricNames[$i]] = $value['value'] ?? 0;
            }
        }

        return $metrics;
    }

    /**
     * Get valid tokens for GA4.
     */
    private function getValidTokensForSite(Site $site): GoogleTokens
    {
        // GA4 uses the same OAuth tokens as GSC
        if (!$site->ga4_token) {
            // Fall back to GSC tokens if GA4 specific tokens not set
            $tokens = $this->authService->getValidTokensForSite($site);
            if (!$tokens) {
                throw new \RuntimeException('Site not connected to Google');
            }
            return $tokens;
        }

        $tokens = GoogleTokens::fromArray([
            'access_token' => $site->ga4_token,
            'refresh_token' => $site->ga4_refresh_token,
            'expires_at' => $site->ga4_token_expires_at?->timestamp,
        ]);

        if ($tokens->isExpired() && $tokens->refreshToken) {
            $tokens = $this->authService->refreshToken($tokens->refreshToken);

            $site->update([
                'ga4_token' => $tokens->accessToken,
                'ga4_token_expires_at' => $tokens->expiresAt
                    ? now()->setTimestamp($tokens->expiresAt)
                    : null,
            ]);
        }

        return $tokens;
    }
}
