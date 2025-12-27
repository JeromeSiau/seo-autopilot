<?php

namespace Tests\Browser;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class TeamCreationTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_user_without_team_is_redirected_to_team_creation(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/dashboard')
                ->waitForLocation('/teams/create')
                ->assertPathIs('/teams/create')
                ->assertPresent('[data-testid="team-create-form"]');
        });
    }

    public function test_user_can_create_team(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/teams/create')
                ->assertPresent('[data-testid="team-create-form"]')
                ->type('#name', 'My Awesome Company')
                ->click('[data-testid="team-create-submit"]')
                ->waitForLocation('/dashboard')
                ->assertPathIs('/dashboard');
        });

        // Verify team was created
        $this->assertDatabaseHas('teams', [
            'name' => 'My Awesome Company',
            'owner_id' => $user->id,
        ]);

        // Verify user is now associated with the team
        $user->refresh();
        $this->assertNotNull($user->current_team_id);
        $this->assertEquals('My Awesome Company', $user->currentTeam->name);
        $this->assertTrue($user->currentTeam->is_trial);
    }

    public function test_registration_leads_to_team_creation(): void
    {
        $this->browse(function (Browser $browser) {
            // Register and verify redirect to team creation
            $browser->visit('/register')
                ->type('#name', 'New User')
                ->type('#email', 'newuser@example.com')
                ->type('#password', 'password123')
                ->type('#password_confirmation', 'password123')
                ->click('[data-testid="register-submit"]')
                ->waitForLocation('/teams/create', 10)
                ->assertPathIs('/teams/create')
                ->assertPresent('[data-testid="team-create-form"]');
        });

        // Verify user was created without team
        $user = User::where('email', 'newuser@example.com')->first();
        $this->assertNotNull($user);
        $this->assertNull($user->current_team_id);
    }

    public function test_team_name_is_required(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/teams/create')
                ->click('[data-testid="team-create-submit"]')
                ->assertPathIs('/teams/create');
        });
    }

    public function test_user_cannot_access_other_routes_without_team(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            // Try to access onboarding
            $browser->loginAs($user)
                ->visit('/onboarding')
                ->waitForLocation('/teams/create')
                ->assertPathIs('/teams/create');

            // Try to access sites
            $browser->visit('/sites')
                ->waitForLocation('/teams/create')
                ->assertPathIs('/teams/create');

            // Try to access settings
            $browser->visit('/settings')
                ->waitForLocation('/teams/create')
                ->assertPathIs('/teams/create');
        });
    }
}
