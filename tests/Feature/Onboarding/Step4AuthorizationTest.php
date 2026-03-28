<?php

namespace Tests\Feature\Onboarding;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTeams;
use Tests\TestCase;

class Step4AuthorizationTest extends TestCase
{
    use CreatesTeams;
    use RefreshDatabase;

    public function test_step4_rejects_updates_for_foreign_sites(): void
    {
        $owner = $this->createUserWithTeam();
        $site = $this->createSiteForUser($owner);

        $intruder = $this->createUserWithTeam();

        $response = $this->actingAs($intruder)->postJson(route('onboarding.step4', $site), [
            'articles_per_week' => 3,
            'publish_days' => ['mon', 'wed'],
            'auto_publish' => true,
        ]);

        $response->assertForbidden();
    }
}
