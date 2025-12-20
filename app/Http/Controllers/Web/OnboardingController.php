<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\SiteSetting;
use App\Jobs\DiscoverKeywordsJob;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OnboardingController extends Controller
{
    public function create()
    {
        return Inertia::render('Onboarding/Wizard', [
            'team' => auth()->user()->team,
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
        $site->update(['onboarding_completed_at' => now()]);

        // Enable autopilot
        SiteSetting::updateOrCreate(
            ['site_id' => $site->id],
            ['autopilot_enabled' => true]
        );

        // Queue initial keyword discovery
        DiscoverKeywordsJob::dispatch($site);

        return redirect()->route('dashboard')
            ->with('success', 'Autopilot activé ! La découverte de keywords a commencé.');
    }
}
