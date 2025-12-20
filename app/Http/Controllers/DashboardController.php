<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Models\Article;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $team = $user->team;

        $sites = $team->sites()->with(['settings', 'keywords', 'articles'])->get();

        // Aggregate stats
        $stats = [
            'total_sites' => $sites->count(),
            'active_sites' => $sites->filter(fn($s) => $s->isAutopilotActive())->count(),
            'total_keywords_queued' => $sites->sum(fn($s) => $s->keywords()->where('status', 'queued')->count()),
            'articles_this_month' => $sites->sum(fn($s) => $s->articles()->where('created_at', '>=', now()->startOfMonth())->count()),
            'articles_published_this_month' => $sites->sum(fn($s) => $s->articles()->where('status', 'published')->where('published_at', '>=', now()->startOfMonth())->count()),
            'articles_used' => $team->articlesUsedThisMonth(),
            'articles_limit' => $team->articles_limit,
        ];

        // Sites with status
        $sitesData = $sites->map(fn($site) => [
            'id' => $site->id,
            'domain' => $site->domain,
            'name' => $site->name,
            'autopilot_status' => $this->getAutopilotStatus($site),
            'articles_per_week' => $site->settings?->articles_per_week ?? 0,
            'articles_in_review' => $site->articles()->where('status', 'review')->count(),
            'articles_this_week' => $site->articles()->where('created_at', '>=', now()->startOfWeek())->count(),
            'onboarding_complete' => $site->isOnboardingComplete(),
        ]);

        // Actions required
        $actionsRequired = $this->getActionsRequired($sites);

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'sites' => $sitesData,
            'actionsRequired' => $actionsRequired,
            'unreadNotifications' => $user->unreadNotificationsCount(),
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
