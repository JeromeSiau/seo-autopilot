<?php

namespace Tests\Feature\Onboarding;

use App\Models\Site;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class Step1DomainCheckTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_blocks_registration_if_domain_exists(): void
    {
        $existingUser = User::factory()->create();
        $existingTeam = Team::factory()->create(['owner_id' => $existingUser->id]);
        Site::factory()->create([
            'team_id' => $existingTeam->id,
            'domain' => 'example.com',
        ]);

        $user = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $user->id]);
        $user->update(['team_id' => $team->id]);

        $response = $this->actingAs($user)->post(route('onboarding.step1'), [
            'domain' => 'example.com',
            'name' => 'My Site',
            'language' => 'fr',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('domain');
    }

    public function test_allows_registration_for_new_domain(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $user->id]);
        $user->update(['team_id' => $team->id]);

        $response = $this->actingAs($user)->post(route('onboarding.step1'), [
            'domain' => 'brand-new-domain-' . time() . '.com',
            'name' => 'My Site',
            'language' => 'fr',
        ]);

        $response->assertStatus(200);
    }
}
