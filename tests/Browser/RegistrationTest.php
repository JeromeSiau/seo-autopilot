<?php

namespace Tests\Browser;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class RegistrationTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_user_can_register(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/register')
                ->assertPresent('[data-testid="register-form"]')
                ->type('#name', 'Test User')
                ->type('#email', 'test@example.com')
                ->type('#password', 'password123')
                ->type('#password_confirmation', 'password123')
                ->click('[data-testid="register-submit"]')
                ->waitForLocation('/teams/create')
                ->assertPathIs('/teams/create');
        });

        // Verify user was created
        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'name' => 'Test User',
        ]);

        // Verify no team was created yet
        $user = User::where('email', 'test@example.com')->first();
        $this->assertNull($user->current_team_id);
    }

    public function test_user_cannot_register_with_existing_email(): void
    {
        User::factory()->create(['email' => 'existing@example.com']);

        $this->browse(function (Browser $browser) {
            $browser->logout()
                ->visit('/register')
                ->type('#name', 'Test User')
                ->type('#email', 'existing@example.com')
                ->type('#password', 'password123')
                ->type('#password_confirmation', 'password123')
                ->click('[data-testid="register-submit"]')
                ->pause(2000)
                // Should stay on register page with error
                ->assertPathIs('/register');
        });
    }

    public function test_user_cannot_register_with_mismatched_passwords(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->logout()
                ->visit('/register')
                ->type('#name', 'Test User')
                ->type('#email', 'mismatch@example.com')
                ->type('#password', 'password123')
                ->type('#password_confirmation', 'different123')
                ->click('[data-testid="register-submit"]')
                ->pause(2000)
                // Should stay on register page with error
                ->assertPathIs('/register');
        });
    }

    public function test_user_cannot_register_with_weak_password(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->logout()
                ->visit('/register')
                ->type('#name', 'Test User')
                ->type('#email', 'weak@example.com')
                ->type('#password', '123')
                ->type('#password_confirmation', '123')
                ->click('[data-testid="register-submit"]')
                ->pause(2000)
                // Should stay on register page with error
                ->assertPathIs('/register');
        });
    }
}
