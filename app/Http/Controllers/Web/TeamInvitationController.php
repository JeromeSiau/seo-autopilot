<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class TeamInvitationController extends Controller
{
    /**
     * Send a new team invitation.
     */
    public function store(Request $request, Team $team): RedirectResponse
    {
        $this->authorize('invite', $team);

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', 'string', 'in:admin,member'],
        ]);

        // Check if user is already a member
        $existingMember = $team->users()->where('email', $validated['email'])->exists();
        if ($existingMember) {
            return back()->withErrors([
                'email' => 'This user is already a member of this team.',
            ]);
        }

        // Check if there's already a pending invitation
        $existingInvitation = $team->invitations()
            ->where('email', $validated['email'])
            ->where('expires_at', '>', now())
            ->exists();

        if ($existingInvitation) {
            return back()->withErrors([
                'email' => 'An invitation has already been sent to this email.',
            ]);
        }

        // Create the invitation
        $invitation = $team->invitations()->create([
            'email' => $validated['email'],
            'role' => $validated['role'],
        ]);

        // Send invitation email
        Mail::to($validated['email'])->send(
            new \App\Mail\TeamInvitationMail($invitation)
        );

        return back()->with('success', 'Invitation sent successfully.');
    }

    /**
     * Delete a pending invitation.
     */
    public function destroy(Request $request, Team $team, TeamInvitation $invitation): RedirectResponse
    {
        $this->authorize('cancelInvitation', [$team, $invitation]);

        $invitation->delete();

        return back()->with('success', 'Invitation cancelled.');
    }

    /**
     * Accept a team invitation.
     */
    public function accept(Request $request, string $token): RedirectResponse|Response
    {
        $invitation = TeamInvitation::where('token', $token)->first();

        // Invalid token
        if (! $invitation) {
            return Inertia::render('Teams/InvitationError', [
                'error' => 'invalid',
                'message' => 'This invitation link is invalid.',
            ]);
        }

        // Expired invitation
        if ($invitation->isExpired()) {
            return Inertia::render('Teams/InvitationError', [
                'error' => 'expired',
                'message' => 'This invitation has expired.',
            ]);
        }

        $user = $request->user();

        // Not logged in
        if (! $user) {
            // Store invitation token in session for after auth
            session(['pending_invitation' => $token]);

            // Check if a user with this email exists
            $existingUser = User::where('email', $invitation->email)->exists();

            if ($existingUser) {
                // Redirect to login
                return redirect()->route('login')
                    ->with('message', 'Please log in to accept your team invitation.');
            }

            // Redirect to register with email pre-filled
            return redirect()->route('register', ['email' => $invitation->email])
                ->with('message', 'Please create an account to accept your team invitation.');
        }

        // Logged in but wrong email
        if ($user->email !== $invitation->email) {
            return Inertia::render('Teams/InvitationError', [
                'error' => 'email_mismatch',
                'message' => 'This invitation was sent to a different email address. Please log in with the correct account.',
            ]);
        }

        // All checks passed - accept the invitation
        $team = $invitation->team;

        // Attach user to team with the invited role
        $team->users()->attach($user->id, [
            'role' => $invitation->role,
        ]);

        // Set as current team if user doesn't have one
        if (! $user->current_team_id) {
            $user->update(['current_team_id' => $team->id]);
        }

        // Delete the invitation
        $invitation->delete();

        return redirect()->route('dashboard')
            ->with('success', 'You have joined ' . $team->name . '!');
    }
}
