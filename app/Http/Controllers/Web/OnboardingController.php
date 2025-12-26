<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Jobs\SiteIndexJob;
use App\Models\Site;
use App\Models\SiteSetting;
use App\Jobs\DiscoverKeywordsJob;
use App\Services\Crawler\SiteCrawlerService;
use App\Services\Google\GoogleAuthService;
use App\Services\Google\SearchConsoleService;
use App\Services\Google\GA4Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class OnboardingController extends Controller
{
    public function __construct(
        private readonly SiteCrawlerService $crawler,
    ) {}

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

        // PRIORITY: If GSC is connected but no property selected, force step 2 for property selection
        // This happens when user (re)connects Google account
        if ($site->isGscConnected() && !$site->gsc_property_id) {
            $step = 2;
        }
        // If GSC is connected AND property is selected, continue with normal flow
        elseif ($site->isGscConnected() && $site->gsc_property_id) {
            $step = 3;

            // If business description is filled, move to step 4
            if ($site->business_description) {
                $step = 4;
            }

            // If settings exist, move to step 5
            if ($site->settings) {
                $step = 5;
            }

            // If has integration OR step 5 was visited (settings exist), move to step 6
            if ($site->settings && $site->integrations()->exists()) {
                $step = 6;
            }
        }
        // If GSC not connected, still check if other steps were completed
        else {
            // If business description is filled, move to step 4
            if ($site->business_description) {
                $step = 4;
            }

            // If settings exist, move to step 5
            if ($site->settings) {
                $step = 5;
            }

            // If has integration, move to step 6
            if ($site->settings && $site->integrations()->exists()) {
                $step = 6;
            }
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

        // Normalize domain (remove www. prefix)
        $domain = preg_replace('/^www\./', '', strtolower($validated['domain']));
        $validated['domain'] = $domain;

        // Check if domain already exists (anti-abuse protection)
        $existingSite = Site::where('domain', $domain)->first();
        if ($existingSite) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => [
                    'domain' => ['Ce site est déjà enregistré. Contactez support@seo-autopilot.com si vous êtes le propriétaire.'],
                ],
            ], 422);
        }

        // Créer le site avec status running
        $site = Site::create([
            'team_id' => auth()->user()->team_id,
            'crawl_status' => 'running',
            'crawl_pages_count' => 0,
            ...$validated,
        ]);

        // Crawl rapide du sitemap (sync) - stocke dans site_pages MySQL
        try {
            $this->crawler->crawl($site);
            $this->crawler->extractTitlesForPages($site, 50);

            $pagesCount = $site->pages()->count();
            $site->update([
                'crawl_status' => 'partial',
                'crawl_pages_count' => $pagesCount,
            ]);
        } catch (\Exception $e) {
            // Le crawl sitemap a échoué, on continue quand même
            Log::warning('Sitemap crawl failed', ['site_id' => $site->id, 'error' => $e->getMessage()]);
        }

        // Lancer le crawl profond avec embeddings (async)
        SiteIndexJob::dispatch($site, delta: false)->onQueue('crawl');

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
        if ($site->team_id !== auth()->user()->team_id) {
            abort(403);
        }

        $validated = $request->validate([
            'business_description' => 'required|string|max:2000',
            'target_audience' => 'nullable|string|max:500',
            'topics' => 'nullable|array|max:10',
            'topics.*' => 'string|max:100',
            'tone' => 'nullable|string|in:professional,casual,expert,friendly,neutral',
            'writing_style' => 'nullable|string|max:500',
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
