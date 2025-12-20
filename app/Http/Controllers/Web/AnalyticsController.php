<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Resources\SiteResource;
use App\Jobs\SyncSiteAnalyticsJob;
use App\Models\Site;
use App\Models\SiteAnalytic;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsController extends Controller
{
    public function index(Request $request): Response
    {
        $team = $request->user()->currentTeam;
        $sites = $team->sites()->get();

        $selectedSite = null;
        $analyticsData = collect();
        $summary = $this->getEmptySummary();
        $topPages = [];
        $topQueries = [];
        $dateRange = $request->get('range', '28');

        if ($request->filled('site_id')) {
            $selectedSite = $sites->find($request->site_id);

            if ($selectedSite) {
                $days = (int) $dateRange;
                $startDate = now()->subDays($days);

                // Get analytics data
                $analyticsData = SiteAnalytic::where('site_id', $selectedSite->id)
                    ->where('date', '>=', $startDate)
                    ->orderBy('date')
                    ->get()
                    ->map(fn ($row) => [
                        'date' => $row->date->format('Y-m-d'),
                        'clicks' => $row->clicks,
                        'impressions' => $row->impressions,
                        'position' => round($row->position, 1),
                    ]);

                // Calculate summary
                $currentPeriod = SiteAnalytic::where('site_id', $selectedSite->id)
                    ->where('date', '>=', $startDate)
                    ->selectRaw('SUM(clicks) as total_clicks, SUM(impressions) as total_impressions, AVG(position) as avg_position')
                    ->first();

                $previousPeriod = SiteAnalytic::where('site_id', $selectedSite->id)
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

                $summary = [
                    'total_clicks' => (int) $totalClicks,
                    'total_impressions' => (int) $totalImpressions,
                    'avg_position' => round($currentPeriod->avg_position ?? 0, 1),
                    'avg_ctr' => round($avgCtr, 2),
                    'clicks_change' => $clicksChange,
                    'impressions_change' => $impressionsChange,
                ];

                // TODO: Get top pages and queries from GSC API
                // For now, return empty arrays
            }
        }

        return Inertia::render('Analytics/Index', [
            'sites' => SiteResource::collection($sites)->resolve(),
            'selectedSite' => $selectedSite ? new SiteResource($selectedSite) : null,
            'analyticsData' => $analyticsData,
            'summary' => $summary,
            'topPages' => $topPages,
            'topQueries' => $topQueries,
            'dateRange' => $dateRange,
        ]);
    }

    public function sync(Site $site): RedirectResponse
    {
        $this->authorize('update', $site);

        SyncSiteAnalyticsJob::dispatch($site, 28);

        return back()->with('success', 'Analytics sync started.');
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
}
