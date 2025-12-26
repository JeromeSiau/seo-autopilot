<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    /**
     * Update the team's name.
     */
    public function update(Request $request, Team $team): RedirectResponse
    {
        $this->authorize('update', $team);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $team->update($validated);

        return back()->with('success', 'Team updated successfully.');
    }

    /**
     * Switch the user's current team.
     */
    public function switch(Request $request, Team $team): RedirectResponse
    {
        $user = $request->user();

        if (! $user->belongsToTeam($team)) {
            abort(403, 'You do not belong to this team.');
        }

        $user->switchTeam($team);

        return back()->with('success', 'Switched to ' . $team->name . '.');
    }
}
