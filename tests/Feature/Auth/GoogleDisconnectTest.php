<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesTeams;
use Tests\TestCase;

class GoogleDisconnectTest extends TestCase
{
    use CreatesTeams;
    use RefreshDatabase;

    public function test_disconnect_clears_gsc_and_ga4_state(): void
    {
        $user = $this->createUserWithTeam();
        $site = $this->createSiteForUser($user, [
            'gsc_token' => 'gsc-token',
            'gsc_refresh_token' => 'gsc-refresh',
            'gsc_property_id' => 'sc-property',
            'ga4_token' => 'ga4-token',
            'ga4_refresh_token' => 'ga4-refresh',
            'ga4_property_id' => 'ga4-property',
            'gsc_token_expires_at' => now()->addHour(),
            'ga4_token_expires_at' => now()->addHour(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->post("/api/sites/{$site->id}/google/disconnect");

        $response->assertRedirect(route('sites.show', $site));

        $freshSite = $site->fresh();
        $this->assertNull($freshSite->gsc_token);
        $this->assertNull($freshSite->gsc_refresh_token);
        $this->assertNull($freshSite->gsc_property_id);
        $this->assertNull($freshSite->gsc_token_expires_at);
        $this->assertNull($freshSite->ga4_token);
        $this->assertNull($freshSite->ga4_refresh_token);
        $this->assertNull($freshSite->ga4_property_id);
        $this->assertNull($freshSite->ga4_token_expires_at);
    }
}
