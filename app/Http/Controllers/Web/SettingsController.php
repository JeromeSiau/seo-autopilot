<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
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
                'is_trial' => $team->is_trial,
                'trial_ends_at' => $team->trial_ends_at?->toISOString(),
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

    public function team(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        $team = $user->currentTeam;

        if (!$team) {
            return redirect()->route('dashboard')->with('error', 'No team selected.');
        }

        // Get members with their role (owner gets 'owner' role regardless of pivot)
        $members = $team->users()->withPivot('role', 'created_at')->get()->map(fn ($member) => [
            'id' => $member->id,
            'name' => $member->name,
            'email' => $member->email,
            'role' => $member->id === $team->owner_id ? 'owner' : $member->pivot->role,
            'joined_at' => $member->pivot->created_at,
        ]);

        // Get pending invitations (not expired)
        $invitations = $team->invitations()
            ->where('expires_at', '>', now())
            ->get()
            ->map(fn ($inv) => [
                'id' => $inv->id,
                'email' => $inv->email,
                'role' => $inv->role,
                'created_at' => $inv->created_at,
                'expires_at' => $inv->expires_at,
            ]);

        // Determine current user's role
        $userRole = $user->id === $team->owner_id
            ? 'owner'
            : $user->teams()->where('team_id', $team->id)->first()?->pivot?->role ?? 'member';

        return Inertia::render('Settings/Team', [
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'owner_id' => $team->owner_id,
            ],
            'members' => $members,
            'invitations' => $invitations,
            'userRole' => $userRole,
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
