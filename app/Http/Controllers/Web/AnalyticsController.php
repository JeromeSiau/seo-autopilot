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
        $connectedSites = $sites->filter(fn ($site) => $site->isGscConnected());

        $selectedSite = null;
        $analyticsData = collect();
        $summary = $this->getEmptySummary();
        $topPages = [];
        $topQueries = [];
        $dateRange = $request->get('range', '28');
        $days = (int) $dateRange;
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
