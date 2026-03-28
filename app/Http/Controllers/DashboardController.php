<?php

namespace App\Http\Controllers;

use App\Http\Resources\DashboardSiteResource;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $team = $user->currentTeam;

        $sites = $team->sites()
            ->withCount([
                'keywords as total_keywords_count',
                'keywords as queued_keywords_count' => fn ($q) => $q->where('status', 'queued'),
                'articles as total_articles_count',
                'articles as review_articles_count' => fn ($q) => $q->where('status', 'review'),
                'articles as failed_articles_count' => fn ($q) => $q->where('status', 'failed'),
                'articles as this_month_articles_count' => fn ($q) => $q->where('created_at', '>=', now()->startOfMonth()),
                'articles as this_week_articles_count' => fn ($q) => $q->where('created_at', '>=', now()->startOfWeek()),
            ])
            ->with(['settings', 'latestContentPlanGeneration'])
            ->get();

        // Calculate sites limit based on plan (1 for trial, or plan limit)
        $sitesLimit = $team->billingPlan?->sites_limit ?? 1;
        if ($sitesLimit === -1) {
            $sitesLimit = '∞';
        }

        // For trial: count all articles during trial period; for paid: count this month
        if ($team->is_trial && !$team->billingPlan) {
            $articlesUsed = $sites->sum('total_articles_count');
        } else {
            $articlesUsed = $team->articlesUsedThisMonth();
        }

        $stats = [
            'active_sites' => $sites->count(),
            'total_sites' => $sitesLimit,
            'total_keywords' => $sites->sum('total_keywords_count'),
            'total_keywords_queued' => $sites->sum('queued_keywords_count'),
            'total_articles' => $sites->sum('total_articles_count'),
            'articles_this_month' => $sites->sum('this_month_articles_count'),
            'articles_published_this_month' => $sites->sum(fn ($site) => $site->articles()->where('status', 'published')->where('created_at', '>=', now()->startOfMonth())->count()),
            'articles_used' => $articlesUsed,
            'articles_limit' => $team->articles_limit,
        ];

        $actionsRequired = [];
        foreach ($sites as $site) {
            if ($site->review_articles_count > 0) {
                $actionsRequired[] = [
                    'type' => 'review',
                    'site_id' => $site->id,
                    'site_domain' => $site->domain,
                    'count' => $site->review_articles_count,
                    'message' => "{$site->review_articles_count} article(s) en attente de review",
                    'action_url' => route('sites.show', $site->id) . '?tab=review',
                ];
            }

            if ($site->failed_articles_count > 0) {
                $actionsRequired[] = [
                    'type' => 'failed',
                    'site_id' => $site->id,
                    'site_domain' => $site->domain,
                    'count' => $site->failed_articles_count,
                    'message' => "Échec de publication ({$site->failed_articles_count})",
                    'action_url' => route('sites.show', $site->id) . '?tab=failed',
                ];
            }
        }

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'sites' => DashboardSiteResource::collection($sites)->resolve(),
            'actionsRequired' => $actionsRequired,
        ]);
    }
}
