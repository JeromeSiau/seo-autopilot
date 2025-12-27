<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        // User is redirected to dashboard, but middleware will redirect to teams.create
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_new_user_has_no_team_after_registration(): void
    {
        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::where('email', 'test@example.com')->first();

        $this->assertNull($user->current_team_id);
        $this->assertNull($user->currentTeam);
    }

    public function test_new_team_has_7_day_trial(): void
    {
        // Register user
        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::where('email', 'test@example.com')->first();

        // Create team manually (simulating user creating team after registration)
        $team = $user->createTeam('Test Team');

        $this->assertTrue($team->is_trial);
        $this->assertNotNull($team->trial_ends_at);
        $this->assertTrue($team->trial_ends_at->isAfter(now()->addDays(6)));
        $this->assertTrue($team->trial_ends_at->isBefore(now()->addDays(8)));
    }
}
