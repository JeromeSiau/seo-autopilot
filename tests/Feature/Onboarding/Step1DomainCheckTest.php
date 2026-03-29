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

    private function createUserWithTeam(): User
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'owner']);
        $user->update(['current_team_id' => $team->id]);

        return $user;
    }

    public function test_blocks_registration_if_domain_exists(): void
    {
        $existingUser = $this->createUserWithTeam();
        Site::factory()->create([
            'team_id' => $existingUser->currentTeam->id,
            'domain' => 'example.com',
        ]);

        $user = $this->createUserWithTeam();

        $response = $this->actingAs($user)->post(route('onboarding.step1'), [
            'domain' => 'example.com',
            'name' => 'My Site',
            'language' => 'fr',
            'mode' => Site::MODE_EXTERNAL,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('domain');
    }

    public function test_allows_registration_for_new_domain(): void
    {
        $user = $this->createUserWithTeam();

        $response = $this->actingAs($user)->post(route('onboarding.step1'), [
            'domain' => 'brand-new-domain-' . time() . '.com',
            'name' => 'My Site',
            'language' => 'fr',
            'mode' => Site::MODE_EXTERNAL,
        ]);

        $response->assertStatus(200);
    }

    public function test_creates_hosted_site_with_default_hosting_and_pages(): void
    {
        $user = $this->createUserWithTeam();

        $response = $this->actingAs($user)->post(route('onboarding.step1'), [
            'domain' => 'hosted-' . time() . '.com',
            'name' => 'Hosted Site',
            'language' => 'fr',
            'mode' => Site::MODE_HOSTED,
        ]);

        $response->assertOk()
            ->assertJson([
                'mode' => Site::MODE_HOSTED,
            ]);

        $site = Site::query()
            ->with(['hosting', 'hostedPages', 'integrations'])
            ->latest('id')
            ->firstOrFail();

        $this->assertTrue($site->isHosted());
        $this->assertSame('completed', $site->crawl_status);
        $this->assertNotNull($site->hosting);
        $this->assertCount(3, $site->hostedPages);
        $this->assertSame('hosted', $site->integrations->first()?->type);
    }
}
