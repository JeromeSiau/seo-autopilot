<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(Request $request): Response
    {
        $team = $request->user()->currentTeam;

        return Inertia::render('Settings/Index', [
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'plan' => $team->plan,
                'articles_limit' => $team->articles_limit,
                'articles_generated_count' => $team->articles_generated_count,
            ],
        ]);
    }

    public function billing(Request $request): Response
    {
        $team = $request->user()->currentTeam;

        return Inertia::render('Settings/Billing', [
            'team' => $team,
            'plans' => $this->getPlans(),
        ]);
    }

    public function team(Request $request): Response
    {
        $team = $request->user()->currentTeam;

        return Inertia::render('Settings/Team', [
            'team' => $team->load('users'),
        ]);
    }

    public function apiKeys(Request $request): Response
    {
        return Inertia::render('Settings/ApiKeys', [
            'hasOpenAI' => !empty(config('services.openai.api_key')),
            'hasAnthropic' => !empty(config('services.anthropic.api_key')),
            'hasReplicate' => !empty(config('services.replicate.api_key')),
            'hasDataForSEO' => !empty(config('services.dataforseo.login')),
        ]);
    }

    public function notifications(Request $request): Response
    {
        return Inertia::render('Settings/Notifications', [
            'settings' => $request->user()->notification_settings ?? [],
        ]);
    }

    private function getPlans(): array
    {
        return [
            [
                'id' => 'starter',
                'name' => 'Starter',
                'price' => 49,
                'articles' => 25,
                'features' => ['5 sites', 'Basic analytics', 'Email support'],
            ],
            [
                'id' => 'pro',
                'name' => 'Pro',
                'price' => 99,
                'articles' => 100,
                'features' => ['Unlimited sites', 'Advanced analytics', 'Priority support', 'Custom brand voices'],
            ],
            [
                'id' => 'enterprise',
                'name' => 'Enterprise',
                'price' => 299,
                'articles' => 500,
                'features' => ['Everything in Pro', 'API access', 'Dedicated support', 'Custom integrations'],
            ],
        ];
    }
}
