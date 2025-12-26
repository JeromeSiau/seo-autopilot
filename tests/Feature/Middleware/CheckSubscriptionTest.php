<?php

namespace Tests\Feature\Middleware;

use App\Models\Plan;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_allows_get_requests_when_trial_expired(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->trialExpired()->create(['owner_id' => $user->id]);
        $user->update(['team_id' => $team->id]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertStatus(200);
    }

    public function test_blocks_post_requests_when_trial_expired(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->trialExpired()->create(['owner_id' => $user->id]);
        $user->update(['team_id' => $team->id]);

        $response = $this->actingAs($user)->post(route('sites.store'), [
            'domain' => 'test.com',
            'language' => 'fr',
        ]);

        $response->assertRedirect(route('settings.billing'));
    }

    public function test_allows_billing_routes_when_trial_expired(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->trialExpired()->create(['owner_id' => $user->id]);
        $user->update(['team_id' => $team->id]);

        $response = $this->actingAs($user)->get(route('settings.billing'));

        // Should not redirect (200 or whatever billing returns)
        $this->assertNotEquals(302, $response->status());
    }

    public function test_allows_all_requests_with_active_trial(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create([
            'owner_id' => $user->id,
            'is_trial' => true,
            'trial_ends_at' => now()->addDays(5),
        ]);
        $user->update(['team_id' => $team->id]);

        $response = $this->actingAs($user)->post(route('sites.store'), [
            'domain' => 'test-' . time() . '.com',
            'language' => 'fr',
        ]);

        // Should not redirect to billing
        $this->assertNotEquals(route('settings.billing'), $response->headers->get('Location'));
    }

    public function test_allows_all_requests_with_active_subscription(): void
    {
        $plan = Plan::factory()->pro()->create();
        $user = User::factory()->create();
        $team = Team::factory()->create([
            'owner_id' => $user->id,
            'plan_id' => $plan->id,
            'is_trial' => false,
        ]);
        $user->update(['team_id' => $team->id]);

        $response = $this->actingAs($user)->post(route('sites.store'), [
            'domain' => 'test-' . time() . '.com',
            'language' => 'fr',
        ]);

        // Should not redirect to billing (might fail for other reasons but not subscription)
        $this->assertNotEquals(route('settings.billing'), $response->headers->get('Location'));
    }
}
