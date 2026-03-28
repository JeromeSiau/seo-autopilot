<?php

namespace Tests\Concerns;

use App\Models\Site;
use App\Models\Team;
use App\Models\User;

trait CreatesTeams
{
    protected function createUserWithTeam(array $userAttributes = [], array $teamAttributes = []): User
    {
        $user = User::factory()->create($userAttributes);
        $team = Team::factory()->create(array_merge(['owner_id' => $user->id], $teamAttributes));

        $user->teams()->attach($team->id, ['role' => 'owner']);
        $user->update(['current_team_id' => $team->id]);

        return $user->fresh();
    }

    protected function createSiteForUser(User $user, array $attributes = []): Site
    {
        return Site::factory()->create(array_merge([
            'team_id' => $user->currentTeam->id,
        ], $attributes));
    }
}
