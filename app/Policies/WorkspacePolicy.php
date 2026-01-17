<?php

namespace App\Policies;

use App\Enums\UserWorkspace\Role as WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;

class WorkspacePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Workspace $workspace): bool
    {
        return $this->isOwner($user, $workspace) || $workspace->members->contains($user);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Workspace $workspace): bool
    {
        return $this->isOwner($user, $workspace) || $this->isAdmin($user, $workspace);
    }

    public function delete(User $user, Workspace $workspace): bool
    {
        return $this->isOwner($user, $workspace);
    }

    public function restore(User $user, Workspace $workspace): bool
    {
        return $this->isOwner($user, $workspace);
    }

    public function forceDelete(User $user, Workspace $workspace): bool
    {
        return $this->isOwner($user, $workspace);
    }

    public function manageTeam(User $user, Workspace $workspace): bool
    {
        return $this->isOwner($user, $workspace) || $this->isAdmin($user, $workspace);
    }

    public function manageAccounts(User $user, Workspace $workspace): bool
    {
        return $this->isOwner($user, $workspace) || $this->isAdmin($user, $workspace);
    }

    public function createPost(User $user, Workspace $workspace): bool
    {
        return $this->isOwner($user, $workspace) || $workspace->members->contains($user);
    }

    private function isOwner(User $user, Workspace $workspace): bool
    {
        return $user->id === $workspace->user_id;
    }

    private function isAdmin(User $user, Workspace $workspace): bool
    {
        $member = $workspace->members()->where('user_id', $user->id)->first();

        if (! $member) {
            return false;
        }

        return $member->pivot->role === WorkspaceRole::Admin->value;
    }
}
