<?php

namespace App\Http\Controllers\Web;

use App\Models\Plan;
use App\Models\Team;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierController;
use Symfony\Component\HttpFoundation\Response;

class StripeWebhookController extends CashierController
{
    public function handleCustomerSubscriptionCreated(array $payload): Response
    {
        $data = $payload['data']['object'];
        $stripeCustomerId = $data['customer'];
        $stripePriceId = $data['items']['data'][0]['price']['id'] ?? null;

        $team = Team::where('stripe_id', $stripeCustomerId)->first();

        if ($team && $stripePriceId) {
            $plan = Plan::where('stripe_price_id', $stripePriceId)->first();

            if ($plan) {
                $team->update([
                    'plan_id' => $plan->id,
                    'is_trial' => false,
                    'trial_ends_at' => null,
                ]);
            }
        }

        return $this->successMethod();
    }

    public function handleCustomerSubscriptionUpdated(array $payload): Response
    {
        $data = $payload['data']['object'];
        $stripeCustomerId = $data['customer'];
        $stripePriceId = $data['items']['data'][0]['price']['id'] ?? null;

        $team = Team::where('stripe_id', $stripeCustomerId)->first();

        if ($team && $stripePriceId) {
            $plan = Plan::where('stripe_price_id', $stripePriceId)->first();

            if ($plan) {
                $team->update(['plan_id' => $plan->id]);
            }
        }

        return $this->successMethod();
    }

    public function handleCustomerSubscriptionDeleted(array $payload): Response
    {
        $data = $payload['data']['object'];
        $stripeCustomerId = $data['customer'];

        $team = Team::where('stripe_id', $stripeCustomerId)->first();

        if ($team) {
            $team->update([
                'plan_id' => null,
                'is_trial' => true,
                'trial_ends_at' => now(), // Immediately expired
            ]);
        }

        return $this->successMethod();
    }
}
