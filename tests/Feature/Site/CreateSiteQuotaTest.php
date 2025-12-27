<?php

namespace Tests\Feature\Site;

use App\Models\Plan;
use App\Models\Site;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateSiteQuotaTest extends TestCase
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

    public function test_blocks_site_creation_when_quota_reached(): void
    {
        $plan = Plan::factory()->create(['sites_limit' => 1]);
        $user = $this->createUserWithTeam([
            'plan_id' => $plan->id,
            'is_trial' => false,
        ]);

        // Already has 1 site
        Site::factory()->create(['team_id' => $user->currentTeam->id]);

        $response = $this->actingAs($user)->post(route('sites.store'), [
            'domain' => 'newsite.com',
            'name' => 'New Site',
            'language' => 'fr',
        ]);

        $response->assertStatus(403);
    }

    public function test_allows_site_creation_when_under_quota(): void
    {
        $plan = Plan::factory()->create(['sites_limit' => 3]);
        $user = $this->createUserWithTeam([
            'plan_id' => $plan->id,
            'is_trial' => false,
        ]);

        $response = $this->actingAs($user)->post(route('sites.store'), [
            'domain' => 'newsite-' . time() . '.com',
            'name' => 'New Site',
            'language' => 'fr',
        ]);

        // Should not get 403
        $this->assertNotEquals(403, $response->status());
    }

    public function test_allows_site_creation_for_unlimited_plan(): void
    {
        $plan = Plan::factory()->create(['sites_limit' => -1]); // unlimited
        $user = $this->createUserWithTeam([
            'plan_id' => $plan->id,
            'is_trial' => false,
        ]);

        // Already has 5 sites
        Site::factory()->count(5)->create(['team_id' => $user->currentTeam->id]);

        $response = $this->actingAs($user)->post(route('sites.store'), [
            'domain' => 'newsite-' . time() . '.com',
            'name' => 'New Site',
            'language' => 'fr',
        ]);

        // Should not get 403
        $this->assertNotEquals(403, $response->status());
    }

    public function test_allows_trial_user_without_plan_to_create_one_site(): void
    {
        $user = $this->createUserWithTeam([
            'plan_id' => null, // No billing plan
            'is_trial' => true,
        ]);

        $response = $this->actingAs($user)->post(route('sites.store'), [
            'domain' => 'firstsite.com',
            'name' => 'First Site',
            'language' => 'fr',
        ]);

        // Should not get 403 - trial users can create 1 site
        $this->assertNotEquals(403, $response->status());
    }

    public function test_blocks_trial_user_without_plan_from_creating_second_site(): void
    {
        $user = $this->createUserWithTeam([
            'plan_id' => null, // No billing plan
            'is_trial' => true,
        ]);

        // Already has 1 site
        Site::factory()->create(['team_id' => $user->currentTeam->id]);

        $response = $this->actingAs($user)->post(route('sites.store'), [
            'domain' => 'secondsite.com',
            'name' => 'Second Site',
            'language' => 'fr',
        ]);

        $response->assertStatus(403);
    }
}
