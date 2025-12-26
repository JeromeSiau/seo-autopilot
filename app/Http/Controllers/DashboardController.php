<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Models\Article;
use App\Models\ContentPlanGeneration;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $team = $user->currentTeam;

        if (! $team) {
            return redirect()->route('teams.create');
        }

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
            ->with(['settings'])
            ->get();

        $stats = [
            'total_sites' => $sites->count(),
            'total_keywords' => $sites->sum('total_keywords_count'),
            'keywords_in_queue' => $sites->sum('queued_keywords_count'),
            'total_articles' => $sites->sum('total_articles_count'),
            'articles_this_month' => $sites->sum('this_month_articles_count'),
        ];

        $sitesData = $sites->map(fn ($site) => [
            'id' => $site->id,
            'name' => $site->name,
            'domain' => $site->domain,
            'keywords_count' => $site->total_keywords_count,
            'articles_count' => $site->total_articles_count,
            'articles_in_review' => $site->review_articles_count,
            'articles_this_week' => $site->this_week_articles_count,
            'autopilot_enabled' => $site->settings?->autopilot_enabled ?? false,
            'onboarding_completed' => $site->isOnboardingComplete(),
        ]);

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
            'sites' => $sitesData,
            'actionsRequired' => $actionsRequired,
        ]);
    }

    private function getAutopilotStatus(Site $site): string
    {
        if (!$site->isOnboardingComplete()) {
            return 'not_configured';
        }

        if (!$site->isAutopilotActive()) {
            return 'paused';
        }

        $hasErrors = $site->articles()
            ->where('status', 'failed')
            ->where('created_at', '>=', now()->subDay())
            ->exists();

        if ($hasErrors) {
            return 'error';
        }

        return 'active';
    }

    private function getActionsRequired($sites): array
    {
        $actions = [];

        foreach ($sites as $site) {
            $reviewCount = $site->articles()->where('status', 'review')->count();
            if ($reviewCount > 0) {
                $actions[] = [
                    'type' => 'review',
                    'site_id' => $site->id,
                    'site_domain' => $site->domain,
                    'count' => $reviewCount,
                    'message' => "{$reviewCount} article(s) en attente de review",
                    'action_url' => route('sites.show', $site->id) . '?tab=review',
                ];
            }

            $failedCount = $site->articles()->where('status', 'failed')->count();
            if ($failedCount > 0) {
                $actions[] = [
                    'type' => 'failed',
                    'site_id' => $site->id,
                    'site_domain' => $site->domain,
                    'count' => $failedCount,
                    'message' => "Échec de publication ({$failedCount})",
                    'action_url' => route('sites.show', $site->id) . '?tab=failed',
                ];
            }

            if (!$site->isGscConnected() && $site->isOnboardingComplete()) {
                $actions[] = [
                    'type' => 'recommendation',
                    'site_id' => $site->id,
                    'site_domain' => $site->domain,
                    'message' => "Connecter Google Search Console recommandé",
                    'action_url' => route('auth.google', ['site_id' => $site->id]),
                ];
            }
        }

        return $actions;
    }
}
