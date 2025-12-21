<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\SiteSetting;
use App\Jobs\DiscoverKeywordsJob;
use App\Services\Google\GoogleAuthService;
use App\Services\Google\SearchConsoleService;
use App\Services\Google\GA4Service;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OnboardingController extends Controller
{
    public function create()
    {
        return Inertia::render('Onboarding/Wizard', [
            'team' => auth()->user()->team,
            'site' => null,
        ]);
    }

    public function resume(Site $site)
    {
        // Ensure user owns this site
        if ($site->team_id !== auth()->user()->team_id) {
            abort(403);
        }

        // Determine which step to resume from
        $step = 2; // Site already exists, skip step 1

        // If GSC is connected AND property is selected, move to step 3
        // If only connected but no property selected, stay at step 2 to select property
        if ($site->isGscConnected() && $site->gsc_property_id) {
            $step = 3;
        }

        // If business description is filled, move to step 4
        if ($site->business_description) {
            $step = 4;
        }

        // If settings exist, move to step 5
        if ($site->settings) {
            $step = 5;
        }

        // If has integration OR step 5 was visited (settings exist), move to step 6
        if ($site->settings && $site->integration) {
            $step = 6;
        }

        return Inertia::render('Onboarding/Wizard', [
            'team' => auth()->user()->team,
            'site' => $site->load('settings'),
            'resumeStep' => $step,
        ]);
    }

    public function storeStep1(Request $request)
    {
        $validated = $request->validate([
            'domain' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'language' => 'required|string|size:2',
        ]);

        $site = Site::create([
            'team_id' => auth()->user()->team_id,
            ...$validated,
        ]);

        return response()->json(['site_id' => $site->id]);
    }

    public function storeStep2(Request $request, Site $site)
    {
        if ($request->boolean('skip')) {
            return response()->json(['skipped' => true]);
        }

        return response()->json(['redirect' => route('auth.google', ['site_id' => $site->id])]);
    }

    /**
     * Get list of available GSC properties for a site.
     */
    public function getGscSites(Site $site, GoogleAuthService $authService, SearchConsoleService $gscService)
    {
        if ($site->team_id !== auth()->user()->team_id) {
            abort(403);
        }

        if (!$site->isGscConnected()) {
            return response()->json(['sites' => [], 'error' => 'GSC not connected']);
        }

        try {
            $tokens = $authService->getValidTokensForSite($site);
            $gscSites = $gscService->listSites($tokens);

            // Filter to only show verified sites (siteOwner or siteFullUser)
            $verifiedSites = $gscSites->filter(function ($s) {
                return in_array($s->permissionLevel, ['siteOwner', 'siteFullUser']);
            });

            // Try to find a matching property for auto-selection
            $domain = strtolower($site->domain);
            $suggested = null;

            foreach ($verifiedSites as $gscSite) {
                $siteDomain = strtolower($gscSite->getDomain());
                if ($siteDomain === $domain || $siteDomain === 'www.' . $domain) {
                    $suggested = $gscSite->siteUrl;
                    break;
                }
            }

            return response()->json([
                'sites' => $verifiedSites->map(fn($s) => [
                    'url' => $s->siteUrl,
                    'domain' => $s->getDomain(),
                    'permission' => $s->permissionLevel,
                    'is_domain_property' => $s->isDomainProperty(),
                ])->values(),
                'suggested' => $suggested,
                'current' => $site->gsc_property_id,
            ]);
        } catch (\Exception $e) {
            return response()->json(['sites' => [], 'error' => $e->getMessage()]);
        }
    }

    /**
     * Save selected GSC property for a site.
     */
    public function selectGscProperty(Request $request, Site $site)
    {
        if ($site->team_id !== auth()->user()->team_id) {
            abort(403);
        }

        $validated = $request->validate([
            'property_id' => 'required|string|max:255',
        ]);

        $site->update(['gsc_property_id' => $validated['property_id']]);

        return response()->json(['success' => true]);
    }

    /**
     * Get list of available GA4 properties for a site.
     */
    public function getGa4Properties(Site $site, GoogleAuthService $authService, GA4Service $ga4Service)
    {
        if ($site->team_id !== auth()->user()->team_id) {
            abort(403);
        }

        if (!$site->isGscConnected()) {
            return response()->json(['properties' => [], 'error' => 'Google not connected']);
        }

        try {
            $tokens = $authService->getValidTokensForSite($site);
            $properties = $ga4Service->listProperties($tokens);

            // Try to find a matching property for auto-selection
            $domain = strtolower($site->domain);
            $suggested = null;

            foreach ($properties as $prop) {
                $displayName = strtolower($prop['display_name'] ?? '');
                if (str_contains($displayName, $domain) || str_contains($displayName, str_replace('.', '', $domain))) {
                    $suggested = $prop['property_id'];
                    break;
                }
            }

            return response()->json([
                'properties' => $properties->map(fn($p) => [
                    'property_id' => $p['property_id'],
                    'display_name' => $p['display_name'],
                    'account_name' => $p['account_name'],
                ])->values(),
                'suggested' => $suggested,
                'current' => $site->ga4_property_id,
            ]);
        } catch (\Exception $e) {
            return response()->json(['properties' => [], 'error' => $e->getMessage()]);
        }
    }

    /**
     * Save selected GA4 property for a site.
     */
    public function selectGa4Property(Request $request, Site $site)
    {
        if ($site->team_id !== auth()->user()->team_id) {
            abort(403);
        }

        $validated = $request->validate([
            'property_id' => 'required|string|max:255',
        ]);

        $site->update(['ga4_property_id' => $validated['property_id']]);

        return response()->json(['success' => true]);
    }

    public function storeStep3(Request $request, Site $site)
    {
        $validated = $request->validate([
            'business_description' => 'required|string|max:2000',
            'target_audience' => 'nullable|string|max:500',
            'topics' => 'nullable|array',
            'topics.*' => 'string|max:100',
        ]);

        $site->update($validated);

        return response()->json(['success' => true]);
    }

    public function storeStep4(Request $request, Site $site)
    {
        $validated = $request->validate([
            'articles_per_week' => 'required|integer|min:1|max:30',
            'publish_days' => 'required|array|min:1',
            'publish_days.*' => 'string|in:mon,tue,wed,thu,fri,sat,sun',
            'auto_publish' => 'required|boolean',
        ]);

        SiteSetting::updateOrCreate(
            ['site_id' => $site->id],
            $validated
        );

        return response()->json(['success' => true]);
    }

    public function storeStep5(Request $request, Site $site)
    {
        if ($request->boolean('skip')) {
            return response()->json(['skipped' => true]);
        }

        return response()->json([
            'redirect' => route('integrations.create', ['site_id' => $site->id])
        ]);
    }

    public function complete(Site $site)
    {
        if ($site->team_id !== auth()->user()->team_id) {
            abort(403);
        }

        $site->update(['onboarding_completed_at' => now()]);

        SiteSetting::updateOrCreate(
            ['site_id' => $site->id],
            ['autopilot_enabled' => true]
        );

        \App\Jobs\GenerateContentPlanJob::dispatch($site);

        return redirect()->route('onboarding.generating', $site);
    }

    public function generating(Site $site)
    {
        if ($site->team_id !== auth()->user()->team_id) {
            abort(403);
        }

        return Inertia::render('Onboarding/Generating', [
            'site' => $site->only(['id', 'name', 'domain']),
        ]);
    }
}
