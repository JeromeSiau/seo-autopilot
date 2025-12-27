<?php

namespace Tests\Browser;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class AuthTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_user_can_view_register_page(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->logout()
                ->visit('/register')
                ->waitFor('[data-testid="register-form"]')
                ->assertPresent('input#name')
                ->assertPresent('input#email')
                ->assertPresent('input#password')
                ->assertPresent('input#password_confirmation');
        });
    }

    public function test_user_can_register(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->logout()
                ->visit('/register')
                ->waitFor('[data-testid="register-form"]')
                ->type('name', 'Test User')
                ->type('email', 'test@example.com')
                ->type('password', 'password123')
                ->type('password_confirmation', 'password123')
                ->click('[data-testid="register-submit"]')
                ->waitForLocation('/teams/create', 10)
                ->assertPathIs('/teams/create');
        });

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'name' => 'Test User',
        ]);
    }

    public function test_user_can_view_login_page(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->logout()
                ->visit('/login')
                ->waitFor('[data-testid="login-form"]')
                ->assertPresent('input#email')
                ->assertPresent('input#password');
        });
    }

    public function test_user_can_login(): void
    {
        $user = User::factory()->create([
            'email' => 'login@example.com',
            'password' => bcrypt('password123'),
            'email_verified_at' => now(),
        ]);
        $user->createTeam('Test Team');

        $this->browse(function (Browser $browser) {
            $browser->logout()
                ->visit('/login')
                ->waitFor('[data-testid="login-form"]')
                ->waitFor('input#email')
                ->pause(1000);

            // Use JavaScript to set values and submit for React controlled inputs
            $browser->script("
                const emailInput = document.getElementById('email');
                const passwordInput = document.getElementById('password');

                // Set values and dispatch events for React
                const setNativeValue = (element, value) => {
                    const nativeInputValueSetter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;
                    nativeInputValueSetter.call(element, value);
                    element.dispatchEvent(new Event('input', { bubbles: true }));
                    element.dispatchEvent(new Event('change', { bubbles: true }));
                };

                setNativeValue(emailInput, 'login@example.com');
                setNativeValue(passwordInput, 'password123');
            ");

            $browser->pause(500);

            // Submit the form via JavaScript
            $browser->script("document.querySelector('[data-testid=\"login-submit\"]').click();");

            $browser->waitForLocation('/dashboard', 10)
                ->assertPathIs('/dashboard');
        });
    }

    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'valid@example.com',
            'password' => bcrypt('correctpassword'),
        ]);

        $this->browse(function (Browser $browser) {
            $browser->logout()
                ->visit('/login')
                ->waitFor('[data-testid="login-form"]')
                ->type('email', 'valid@example.com')
                ->type('password', 'wrongpassword')
                ->click('[data-testid="login-submit"]')
                ->pause(1000)
                ->assertPathIs('/login');
        });
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $user->createTeam('Test Team');

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/dashboard')
                ->assertPathIs('/dashboard')
                ->logout()
                ->visit('/dashboard')
                ->assertPathIs('/login');
        });
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->logout()
                ->visit('/dashboard')
                ->assertPathIs('/login');
        });
    }

    public function test_register_link_exists_on_login_page(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->logout()
                ->visit('/login')
                ->waitFor('[data-testid="login-register-link"]')
                ->assertAttribute('[data-testid="login-register-link"]', 'href', route('register'));
        });
    }

    public function test_login_link_exists_on_register_page(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->logout()
                ->visit('/register')
                ->waitFor('[data-testid="register-login-link"]')
                ->assertAttribute('[data-testid="register-login-link"]', 'href', route('login'));
        });
    }
}
