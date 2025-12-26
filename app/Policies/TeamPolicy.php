<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;

class TeamPolicy
{
    /**
     * Determine whether the user can view the team.
     */
    public function view(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    /**
     * Determine whether the user can update the team.
     */
    public function update(User $user, Team $team): bool
    {
        return $this->isOwnerOrAdmin($user, $team);
    }

    /**
     * Determine whether the user can invite members to the team.
     */
    public function invite(User $user, Team $team): bool
    {
        return $this->isOwnerOrAdmin($user, $team);
    }

    /**
     * Determine whether the user can cancel invitations.
     */
    public function cancelInvitation(User $user, Team $team): bool
    {
        return $this->isOwnerOrAdmin($user, $team);
    }

    /**
     * Determine whether the user can update a team member's role.
     * Only the owner can change roles.
     */
    public function updateMember(User $user, Team $team, User $member): bool
    {
        return $team->owner_id === $user->id;
    }

    /**
     * Determine whether the user can remove a member from the team.
     * - Can't remove self
     * - Owner can remove anyone (except themselves)
     * - Admin can remove members only (not admins or owner)
     */
    public function removeMember(User $user, Team $team, User $member): bool
    {
        // Can't remove self
        if ($user->id === $member->id) {
            return false;
        }

        // Owner can remove anyone except themselves (already checked above)
        if ($team->owner_id === $user->id) {
            return true;
        }

        // Check if user is admin
        $userRole = $team->users()->where('user_id', $user->id)->first()?->pivot?->role;
        if ($userRole !== 'admin') {
            return false;
        }

        // Admin can only remove members (not admins or owner)
        $memberRole = $team->users()->where('user_id', $member->id)->first()?->pivot?->role;

        return $memberRole === 'member';
    }

    /**
     * Check if the user is the owner or an admin of the team.
     */
    public function isOwnerOrAdmin(User $user, Team $team): bool
    {
        // Check if user is owner
        if ($team->owner_id === $user->id) {
            return true;
        }

        // Check if user is admin
        $membership = $team->users()->where('user_id', $user->id)->first();

        return $membership?->pivot?->role === 'admin';
    }
}
