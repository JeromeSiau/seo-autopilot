<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Resources\SiteResource;
use App\Models\RefreshRecommendation;
use App\Models\Site;
use App\Services\Refresh\RefreshPlannerService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NeedsRefreshController extends Controller
{
    public function __construct(
        private readonly RefreshPlannerService $planner,
    ) {}

    public function index(Request $request): Response
    {
        $team = $request->user()->currentTeam;
        $sites = $team->sites()->get();
        $selectedSite = $request->filled('site_id')
            ? $sites->find($request->integer('site_id'))
            : null;
        $status = $request->string('status', 'active')->value();

        $query = RefreshRecommendation::query()
            ->with(['site', 'article', 'runs'])
            ->whereIn('site_id', $selectedSite ? [$selectedSite->id] : $sites->pluck('id'));

        if ($status === 'active') {
            $query->whereIn('status', [
                RefreshRecommendation::STATUS_OPEN,
                RefreshRecommendation::STATUS_ACCEPTED,
                RefreshRecommendation::STATUS_EXECUTED,
            ]);
        } elseif ($status !== 'all') {
            $query->where('status', $status);
        }

        $recommendations = $query
            ->latest('detected_at')
            ->get();

        return Inertia::render('Articles/NeedsRefresh', [
            'sites' => SiteResource::collection($sites)->resolve(),
            'selectedSite' => $selectedSite ? (new SiteResource($selectedSite))->resolve() : null,
            'selectedStatus' => $status,
            'refreshPlanner' => $this->planner->buildQueue($recommendations),
        ]);
    }
}
