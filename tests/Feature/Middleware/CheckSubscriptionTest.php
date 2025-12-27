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

    private function createUserWithTeam(array $teamAttributes = []): User
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(array_merge([
            'owner_id' => $user->id,
        ], $teamAttributes));
        $user->teams()->attach($team->id, ['role' => 'owner']);
        $user->update(['current_team_id' => $team->id]);

        return $user;
    }

    public function test_allows_get_requests_when_trial_expired(): void
    {
        $user = $this->createUserWithTeam([
            'is_trial' => true,
            'trial_ends_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertStatus(200);
    }

    public function test_blocks_post_requests_when_trial_expired(): void
    {
        $user = $this->createUserWithTeam([
            'is_trial' => true,
            'trial_ends_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($user)->post(route('sites.store'), [
            'domain' => 'test.com',
            'language' => 'fr',
        ]);

        $response->assertRedirect(route('settings.billing'));
    }

    public function test_allows_billing_routes_when_trial_expired(): void
    {
        $user = $this->createUserWithTeam([
            'is_trial' => true,
            'trial_ends_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($user)->get(route('settings.billing'));

        // Should not redirect (200 or whatever billing returns)
        $response->assertStatus(200);
    }

    public function test_allows_all_requests_with_active_trial(): void
    {
        $user = $this->createUserWithTeam([
            'is_trial' => true,
            'trial_ends_at' => now()->addDays(5),
        ]);

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
        $user = $this->createUserWithTeam([
            'plan_id' => $plan->id,
            'is_trial' => false,
        ]);

        $response = $this->actingAs($user)->post(route('sites.store'), [
            'domain' => 'test-' . time() . '.com',
            'language' => 'fr',
        ]);

        // Should not redirect to billing (might fail for other reasons but not subscription)
        $this->assertNotEquals(route('settings.billing'), $response->headers->get('Location'));
    }
}
