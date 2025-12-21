<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Resources\SiteResource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContentPlanController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $team = $user->currentTeam;

        if (!$team) {
            return Inertia::render('ContentPlan/Index', [
                'sites' => [],
            ]);
        }

        $sites = $team->sites()
            ->with(['scheduledArticles' => function ($query) {
                $query->where('scheduled_date', '>=', now()->startOfDay());
            }])
            ->get()
            ->map(function ($site) {
                return [
                    'id' => $site->id,
                    'name' => $site->name,
                    'domain' => $site->domain,
                    'planned_count' => $site->scheduledArticles->where('status', 'planned')->count(),
                    'generated_count' => $site->scheduledArticles->whereIn('status', ['ready', 'generating'])->count(),
                    'published_count' => $site->scheduledArticles->where('status', 'published')->count(),
                    'next_article_date' => $site->scheduledArticles
                        ->where('status', 'planned')
                        ->sortBy('scheduled_date')
                        ->first()
                        ?->scheduled_date
                        ?->toDateString(),
                ];
            });

        return Inertia::render('ContentPlan/Index', [
            'sites' => $sites,
        ]);
    }
}
