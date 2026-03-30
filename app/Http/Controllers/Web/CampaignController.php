<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Resources\CampaignRunResource;
use App\Http\Resources\SiteResource;
use App\Models\CampaignRun;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\Request;

class CampaignController extends Controller
{
    public function index(Request $request): Response
    {
        $team = $request->user()->currentTeam;
        $sites = $team->sites()->get();
        $siteIds = $sites->pluck('id');
        $selectedSite = $request->filled('site_id')
            ? $sites->find($request->integer('site_id'))
            : null;
        $selectedStatus = $request->string('status', 'all')->value();

        $query = CampaignRun::query()
            ->whereIn('site_id', $selectedSite ? [$selectedSite->id] : $siteIds)
            ->with(['site', 'creator'])
            ->latest();

        if ($selectedStatus !== 'all') {
            $query->where('status', $selectedStatus);
        }

        $campaigns = $query->get();

        return Inertia::render('Campaigns/Index', [
            'sites' => SiteResource::collection($sites)->resolve(),
            'selectedSite' => $selectedSite ? (new SiteResource($selectedSite))->resolve() : null,
            'selectedStatus' => $selectedStatus,
            'campaigns' => CampaignRunResource::collection($campaigns)->resolve(),
            'summary' => [
                'total' => $campaigns->count(),
                'pending' => $campaigns->where('status', CampaignRun::STATUS_PENDING)->count(),
                'dispatched' => $campaigns->where('status', CampaignRun::STATUS_DISPATCHED)->count(),
                'completed' => $campaigns->where('status', CampaignRun::STATUS_COMPLETED)->count(),
                'failed' => $campaigns->where('status', CampaignRun::STATUS_FAILED)->count(),
            ],
        ]);
    }
}
