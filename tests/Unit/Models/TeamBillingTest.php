<?php

namespace Tests\Unit\Models;

use App\Models\Plan;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamBillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_belongs_to_billing_plan(): void
    {
        $plan = Plan::factory()->pro()->create();
        $user = User::factory()->create();
        $team = Team::factory()->create([
            'owner_id' => $user->id,
            'plan_id' => $plan->id,
        ]);

        $this->assertEquals($plan->id, $team->billingPlan->id);
    }

    public function test_is_trial_expired_returns_true_when_expired(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create([
            'owner_id' => $user->id,
            'is_trial' => true,
            'trial_ends_at' => now()->subDay(),
        ]);

        $this->assertTrue($team->isTrialExpired());
    }

    public function test_is_trial_expired_returns_false_when_active(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create([
            'owner_id' => $user->id,
            'is_trial' => true,
            'trial_ends_at' => now()->addDays(3),
        ]);

        $this->assertFalse($team->isTrialExpired());
    }

    public function test_is_trial_expired_returns_false_when_not_trial(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create([
            'owner_id' => $user->id,
            'is_trial' => false,
            'trial_ends_at' => null,
        ]);

        $this->assertFalse($team->isTrialExpired());
    }

    public function test_can_create_site_returns_true_when_under_limit(): void
    {
        $plan = Plan::factory()->create(['sites_limit' => 3]);
        $user = User::factory()->create();
        $team = Team::factory()->create([
            'owner_id' => $user->id,
            'plan_id' => $plan->id,
        ]);

        $this->assertTrue($team->canCreateSite());
    }

    public function test_can_create_site_returns_true_for_unlimited(): void
    {
        $plan = Plan::factory()->agency()->create();
        $user = User::factory()->create();
        $team = Team::factory()->create([
            'owner_id' => $user->id,
            'plan_id' => $plan->id,
        ]);

        $this->assertTrue($team->canCreateSite());
    }
}
