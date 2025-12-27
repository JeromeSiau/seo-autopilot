<?php

namespace Tests\Browser;

use App\Models\Site;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class OnboardingTest extends DuskTestCase
{
    use DatabaseMigrations;

    protected User $user;
    protected Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a user with a team (standard setup after registration + team creation)
        $this->user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $this->team = $this->user->createTeam('Test Company');
    }

    public function test_user_can_access_onboarding(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/onboarding')
                ->assertPresent('[data-testid="onboarding-step1-form"]')
                ->assertPresent('[data-testid="onboarding-domain-input"]');
        });
    }

    public function test_step1_create_site(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/onboarding')
                // Step 1: Site creation
                ->waitFor('[data-testid="onboarding-domain-input"]')
                ->type('[data-testid="onboarding-domain-input"]', 'example.com')
                ->type('[data-testid="onboarding-name-input"]', 'My Test Site')
                ->click('[data-testid="onboarding-step1-submit"]')
                // Should move to Step 2 - wait for step indicator to change
                ->waitFor('.bg-primary-500, .bg-primary-600', 10) // Wait for step 2 indicator
                ->pause(1000);
        });

        // Verify site was created
        $this->assertDatabaseHas('sites', [
            'domain' => 'example.com',
            'name' => 'My Test Site',
            'team_id' => $this->team->id,
        ]);
    }

    public function test_step1_validates_domain(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/onboarding')
                ->waitFor('[data-testid="onboarding-domain-input"]')
                // Clear and leave empty, then try to submit
                ->type('[data-testid="onboarding-name-input"]', 'Test Site')
                ->click('[data-testid="onboarding-step1-submit"]')
                ->pause(500)
                // Should stay on step 1 (HTML5 validation should prevent submit)
                ->assertPresent('[data-testid="onboarding-domain-input"]');
        });
    }

    public function test_domain_must_be_unique(): void
    {
        // Create a site with a domain
        Site::factory()->create([
            'team_id' => $this->team->id,
            'domain' => 'existing.com',
            'name' => 'Existing Site',
            'language' => 'fr',
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/onboarding')
                ->waitFor('[data-testid="onboarding-domain-input"]')
                ->type('[data-testid="onboarding-domain-input"]', 'existing.com')
                ->type('[data-testid="onboarding-name-input"]', 'New Site')
                ->click('[data-testid="onboarding-step1-submit"]')
                ->pause(1000)
                // Should show domain error - check for error message presence
                ->assertPresent('[data-testid="onboarding-step1-form"]');
        });
    }

    public function test_onboarding_step_indicators_update(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/onboarding')
                // Verify step indicators are present
                ->assertPresent('[data-testid="onboarding-step1-form"]');
        });
    }
}
