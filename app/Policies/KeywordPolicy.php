<?php

namespace App\Policies;

use App\Models\Keyword;
use App\Models\User;

class KeywordPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Keyword $keyword): bool
    {
        return $user->currentTeam?->id === $keyword->site->team_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->currentTeam !== null;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Keyword $keyword): bool
    {
        return $user->currentTeam?->id === $keyword->site->team_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Keyword $keyword): bool
    {
        return $user->currentTeam?->id === $keyword->site->team_id;
    }

    /**
     * Determine whether the user can generate an article from the keyword.
     */
    public function generate(User $user, Keyword $keyword): bool
    {
        return $user->currentTeam?->id === $keyword->site->team_id;
    }
}
