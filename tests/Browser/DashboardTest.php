<?php

namespace Tests\Browser;

use App\Models\Site;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class DashboardTest extends DuskTestCase
{
    use DatabaseMigrations;

    protected User $user;
    protected Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $this->team = $this->user->createTeam('Test Company');
    }

    public function test_user_can_access_dashboard(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/dashboard')
                ->assertPathIs('/dashboard');
        });
    }

    public function test_dashboard_shows_team_name(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/dashboard')
                ->assertSee('Test Company');
        });
    }

    public function test_dashboard_shows_sites_in_nav(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/dashboard')
                // Sites link should be in navigation
                ->assertPresent('[data-testid="nav-sites"]');
        });
    }

    public function test_dashboard_has_navigation(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/dashboard')
                // Sidebar should have navigation items
                ->assertPresent('[data-testid="nav-dashboard"]')
                ->assertPresent('[data-testid="nav-sites"]');
        });
    }

    public function test_user_can_navigate_to_sites(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/dashboard')
                // Click the Sites link in the desktop sidebar
                ->click('[data-testid="desktop-sidebar"] [data-testid="nav-sites"]')
                ->pause(500)
                ->assertPathIs('/sites');
        });
    }

    public function test_user_can_navigate_to_onboarding(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/dashboard')
                // Look for add site button
                ->click('a[href*="onboarding"]')
                ->pause(500)
                ->assertPathBeginsWith('/onboarding');
        });
    }

    public function test_dashboard_shows_usage_info(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/dashboard')
                // Should show usage section in sidebar
                ->assertPresent('[data-testid="usage-section"]');
        });
    }

    public function test_user_can_access_settings(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/settings')
                ->assertPathBeginsWith('/settings');
        });
    }
}
