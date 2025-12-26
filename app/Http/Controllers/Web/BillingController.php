<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BillingController extends Controller
{
    public function index(Request $request): Response
    {
        $team = $request->user()->currentTeam;

        return Inertia::render('Settings/Billing', [
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'is_trial' => $team->is_trial,
                'trial_ends_at' => $team->trial_ends_at?->toISOString(),
                'plan' => $team->billingPlan,
                'subscribed' => $team->subscribed(),
            ],
            'plans' => Plan::active()->orderBy('sort_order')->get(),
        ]);
    }

    public function checkout(Request $request)
    {
        $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
        ]);

        $plan = Plan::findOrFail($request->plan_id);
        $team = $request->user()->currentTeam;

        if (!$plan->stripe_price_id) {
            return back()->withErrors(['plan_id' => 'Ce plan n\'est pas encore disponible.']);
        }

        $checkout = $team->newSubscription('default', $plan->stripe_price_id)
            ->checkout([
                'success_url' => route('billing.success') . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('billing.cancel'),
            ]);

        return Inertia::location($checkout->url);
    }

    public function success(Request $request)
    {
        $team = $request->user()->currentTeam;

        // Update team to mark trial as complete
        if ($team->is_trial) {
            $team->update([
                'is_trial' => false,
                'trial_ends_at' => null,
            ]);
        }

        return redirect()->route('settings.billing')
            ->with('success', 'Votre abonnement a été activé avec succès !');
    }

    public function cancel()
    {
        return redirect()->route('settings.billing')
            ->with('info', 'Le paiement a été annulé.');
    }

    public function portal(Request $request)
    {
        return Inertia::location(
            $request->user()->currentTeam->billingPortalUrl(route('settings.billing'))
        );
    }
}
