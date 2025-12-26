<?php

namespace App\Policies;

use App\Models\Article;
use App\Models\User;

class ArticlePolicy
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
    public function view(User $user, Article $article): bool
    {
        return $user->currentTeam?->id === $article->site->team_id;
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
    public function update(User $user, Article $article): bool
    {
        return $user->currentTeam?->id === $article->site->team_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Article $article): bool
    {
        return $user->currentTeam?->id === $article->site->team_id;
    }

    /**
     * Determine whether the user can publish the model.
     */
    public function publish(User $user, Article $article): bool
    {
        return $user->currentTeam?->id === $article->site->team_id;
    }

    /**
     * Determine whether the user can approve the model.
     */
    public function approve(User $user, Article $article): bool
    {
        return $user->currentTeam?->id === $article->site->team_id;
    }
}
