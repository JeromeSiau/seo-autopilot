<?php

namespace App\Http\Controllers;

use App\Http\Resources\ArticleResource;
use App\Http\Resources\KeywordResource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $team = $user->currentTeam;

        // Auto-create team if user doesn't have one
        if (!$team) {
            $team = $user->createTeam($user->name . "'s Team");
        }

        $sites = $team->sites()->get();
        $siteIds = $sites->pluck('id');

        // Get stats
        $stats = [
            'total_sites' => $sites->count(),
            'total_keywords' => \App\Models\Keyword::whereIn('site_id', $siteIds)->count(),
            'total_articles' => \App\Models\Article::whereIn('site_id', $siteIds)->count(),
            'articles_published' => \App\Models\Article::whereIn('site_id', $siteIds)
                ->where('status', 'published')
                ->count(),
            'articles_this_month' => \App\Models\Article::whereIn('site_id', $siteIds)
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
            'total_clicks' => \App\Models\SiteAnalytic::whereIn('site_id', $siteIds)->sum('clicks'),
            'total_impressions' => \App\Models\SiteAnalytic::whereIn('site_id', $siteIds)->sum('impressions'),
            'avg_position' => \App\Models\SiteAnalytic::whereIn('site_id', $siteIds)->avg('position') ?? 0,
            'articles_limit' => $team->articles_limit,
            'articles_used' => $team->articles_generated_count,
        ];

        // Recent articles
        $recentArticles = \App\Models\Article::whereIn('site_id', $siteIds)
            ->with(['site', 'keyword'])
            ->latest()
            ->limit(5)
            ->get();

        // Top keywords by score
        $topKeywords = \App\Models\Keyword::whereIn('site_id', $siteIds)
            ->where('status', 'pending')
            ->orderByDesc('score')
            ->limit(5)
            ->get();

        // Analytics data for chart (last 14 days)
        $analyticsData = \App\Models\SiteAnalytic::whereIn('site_id', $siteIds)
            ->where('date', '>=', now()->subDays(14))
            ->selectRaw('date, SUM(clicks) as clicks, SUM(impressions) as impressions, AVG(position) as position')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->date->format('Y-m-d'),
                'clicks' => (int) $row->clicks,
                'impressions' => (int) $row->impressions,
                'position' => round($row->position, 1),
            ]);

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'recentArticles' => ArticleResource::collection($recentArticles)->resolve(),
            'topKeywords' => KeywordResource::collection($topKeywords)->resolve(),
            'analyticsData' => $analyticsData,
        ]);
    }
}
