<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    /**
     * Create a new team.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $user = $request->user();

        // Create team with user as owner
        $team = Team::create([
            'name' => $validated['name'],
            'owner_id' => $user->id,
        ]);

        // Add user to team with owner role
        $user->teams()->attach($team->id, ['role' => 'owner']);

        // Switch to new team
        $user->update(['current_team_id' => $team->id]);

        return back()->with('success', 'Team "' . $team->name . '" created successfully.');
    }

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
