<?php

namespace Tests\Browser;

use App\Models\Site;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class BillingTest extends DuskTestCase
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

    public function test_trial_user_can_access_billing_page(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/billing')
                ->assertPathIs('/billing')
                ->assertPresent('[data-testid="billing-page"]');
        });
    }

    public function test_trial_user_sees_trial_status(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/billing')
                // Should indicate trial status
                ->assertPresent('[data-testid="billing-trial-badge"]');
        });
    }

    public function test_expired_trial_allows_get_requests(): void
    {
        // Expire the trial
        $this->team->update([
            'trial_ends_at' => now()->subDay(),
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                // GET request should still work
                ->visit('/dashboard')
                ->assertPathIs('/dashboard');
        });
    }

    public function test_expired_trial_shows_billing_page(): void
    {
        // Expire the trial
        $this->team->update([
            'trial_ends_at' => now()->subDay(),
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/billing')
                // Should show billing page with plan options
                ->assertPresent('[data-testid="billing-page"]')
                ->assertPresent('[data-testid="billing-plans-section"]');
        });
    }

    public function test_active_trial_allows_all_requests(): void
    {
        // Ensure trial is active (default is 7 days from creation)
        $this->assertTrue($this->team->trial_ends_at->isFuture());

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                // Should be able to access mutation routes
                ->visit('/onboarding')
                ->assertPathIs('/onboarding')

                // Dashboard should work
                ->visit('/dashboard')
                ->assertPathIs('/dashboard');
        });
    }

    public function test_billing_page_shows_subscription_info(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/billing')
                ->assertPathIs('/billing')
                // Should have subscription section
                ->assertPresent('[data-testid="billing-subscription-section"]');
        });
    }

    public function test_trial_days_remaining_displayed(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/billing')
                // Should show days remaining
                ->assertPresent('[data-testid="billing-trial-days"]');
        });
    }

    public function test_settings_billing_link_works(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/settings')
                // Find and click billing link
                ->click('a[href*="billing"]')
                ->waitForLocation('/billing')
                ->assertPathIs('/billing');
        });
    }

    public function test_billing_page_has_faq(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/billing')
                ->assertPresent('[data-testid="billing-faq-section"]');
        });
    }
}
