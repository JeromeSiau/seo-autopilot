<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TeamMemberController extends Controller
{
    /**
     * Update a team member's role.
     */
    public function update(Request $request, Team $team, User $user): RedirectResponse
    {
        $this->authorize('updateMember', [$team, $user]);

        $validated = $request->validate([
            'role' => ['required', 'string', 'in:admin,member'],
        ]);

        $team->users()->updateExistingPivot($user->id, [
            'role' => $validated['role'],
        ]);

        return back()->with('success', 'Member role updated successfully.');
    }

    /**
     * Remove a member from the team.
     */
    public function destroy(Request $request, Team $team, User $user): RedirectResponse
    {
        $this->authorize('removeMember', [$team, $user]);

        // Cannot remove the team owner
        if ($team->owner_id === $user->id) {
            abort(403, 'Cannot remove the team owner.');
        }

        // Detach user from team
        $team->users()->detach($user->id);

        // Clear user's current_team_id if they were viewing this team
        if ($user->current_team_id === $team->id) {
            // Set to their first remaining team, or null if none
            $nextTeam = $user->teams()->first();
            $user->update([
                'current_team_id' => $nextTeam?->id,
            ]);
        }

        return back()->with('success', 'Member removed from team.');
    }
}
