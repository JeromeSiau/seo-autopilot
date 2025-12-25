<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Services\ContentPlan\ContentPlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContentPlanController extends Controller
{
    public function __construct(
        private readonly ContentPlanService $planService,
    ) {}

    public function generationStatus(Site $site): JsonResponse
    {
        if ($site->team_id !== auth()->user()->team_id) {
            abort(403);
        }

        $generation = $site->contentPlanGenerations()
            ->latest()
            ->first();

        if (!$generation) {
            return response()->json([
                'status' => 'not_started',
            ]);
        }

        return response()->json([
            'status' => $generation->status,
            'current_step' => $generation->current_step,
            'total_steps' => $generation->total_steps,
            'steps' => $generation->steps,
            'keywords_found' => $generation->keywords_found,
            'articles_planned' => $generation->articles_planned,
            'error_message' => $generation->error_message,
        ]);
    }

    public function contentPlan(Site $site, Request $request): JsonResponse
    {
        if ($site->team_id !== auth()->user()->team_id) {
            abort(403);
        }

        $month = $request->get('month', now()->format('Y-m'));
        $articles = $this->planService->getCalendarData($site, $month);

        // Get all months that have scheduled articles
        $availableMonths = $site->scheduledArticles()
            ->selectRaw("DATE_FORMAT(scheduled_date, '%Y-%m') as month")
            ->distinct()
            ->orderBy('month')
            ->pluck('month')
            ->toArray();

        return response()->json([
            'month' => $month,
            'articles' => $articles,
            'available_months' => $availableMonths,
        ]);
    }
}
