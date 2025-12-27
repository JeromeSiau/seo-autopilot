<?php

namespace Tests\Browser;

use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class TeamInvitationTest extends DuskTestCase
{
    use DatabaseMigrations;

    protected User $owner;
    protected Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        // Create team owner
        $this->owner = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $this->team = $this->owner->createTeam('Test Company');
    }

    public function test_owner_can_access_team_settings(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->owner)
                ->visit('/settings/team')
                ->assertSee('Test Company')
                ->assertPresent('[data-testid="team-members-section"]');
        });
    }

    public function test_team_settings_shows_invite_section(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->owner)
                ->visit('/settings/team')
                ->assertPresent('[data-testid="team-invite-section"]');
        });
    }

    public function test_team_settings_shows_owner(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->owner)
                ->visit('/settings/team')
                ->assertPresent('[data-testid="member-role-owner"]');
        });
    }

    public function test_logged_in_user_can_accept_invitation(): void
    {
        // Create a new user (no team yet)
        $invitedUser = User::factory()->create([
            'email' => 'invited@example.com',
            'email_verified_at' => now(),
        ]);

        // Create an invitation for this user
        $invitation = TeamInvitation::factory()->create([
            'team_id' => $this->team->id,
            'email' => 'invited@example.com',
            'role' => 'member',
        ]);

        $this->browse(function (Browser $browser) use ($invitation, $invitedUser) {
            $browser->loginAs($invitedUser)
                ->visit("/invitations/{$invitation->token}/accept")
                ->waitForLocation('/dashboard')
                ->assertPathIs('/dashboard');
        });

        // Verify user joined the team
        $invitedUser->refresh();
        $this->assertEquals($this->team->id, $invitedUser->current_team_id);
        $this->assertTrue($this->team->users()->where('user_id', $invitedUser->id)->exists());

        // Verify invitation was deleted
        $this->assertDatabaseMissing('team_invitations', [
            'id' => $invitation->id,
        ]);
    }

    public function test_invalid_invitation_token_shows_error(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/invitations/invalid-token-12345/accept')
                ->assertSee('invalid');
        });
    }

    public function test_team_rename_functionality(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->owner)
                ->visit('/settings/team')
                ->assertPresent('[data-testid="team-rename-section"]');
        });
    }

    public function test_team_settings_has_save_button(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->owner)
                ->visit('/settings/team')
                ->assertPresent('[data-testid="team-rename-submit"]');
        });
    }
}
