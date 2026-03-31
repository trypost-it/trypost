<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserWorkspace\Role;
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
        return $this->isMember($user, $workspace);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Workspace $workspace): bool
    {
        return $this->hasRole($user, $workspace, [Role::Owner, Role::Admin]);
    }

    public function delete(User $user, Workspace $workspace): bool
    {
        return $this->hasRole($user, $workspace, [Role::Owner]);
    }

    public function restore(User $user, Workspace $workspace): bool
    {
        return $this->hasRole($user, $workspace, [Role::Owner]);
    }

    public function forceDelete(User $user, Workspace $workspace): bool
    {
        return $this->hasRole($user, $workspace, [Role::Owner]);
    }

    public function manageTeam(User $user, Workspace $workspace): bool
    {
        return $this->hasRole($user, $workspace, [Role::Owner, Role::Admin]);
    }

    public function manageAccounts(User $user, Workspace $workspace): bool
    {
        return $this->hasRole($user, $workspace, [Role::Owner, Role::Admin]);
    }

    public function createPost(User $user, Workspace $workspace): bool
    {
        return $this->isMember($user, $workspace);
    }

    public function manageBilling(User $user, Workspace $workspace): bool
    {
        return $this->hasRole($user, $workspace, [Role::Owner]);
    }

    /**
     * @param  Role[]  $roles
     */
    private function hasRole(User $user, Workspace $workspace, array $roles): bool
    {
        $member = $workspace->members()->where('user_id', $user->id)->first();

        if (! $member) {
            return false;
        }

        return in_array(Role::tryFrom($member->pivot->role), $roles);
    }

    private function isMember(User $user, Workspace $workspace): bool
    {
        return $workspace->members()->where('user_id', $user->id)->exists();
    }
}
