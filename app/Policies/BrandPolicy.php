<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserWorkspace\Role;
use App\Models\Brand;
use App\Models\User;
use App\Models\Workspace;

class BrandPolicy
{
    public function viewAny(User $user, Workspace $workspace): bool
    {
        return $workspace->members()->where('user_id', $user->id)->exists();
    }

    public function create(User $user, Workspace $workspace): bool
    {
        if (! $this->canManage($user, $workspace)) {
            return false;
        }

        if (config('trypost.self_hosted')) {
            return true;
        }

        $plan = $workspace->plan;

        if (! $plan) {
            return false;
        }

        return $workspace->brands()->count() < $plan->brand_limit;
    }

    public function update(User $user, Brand $brand): bool
    {
        return $this->canManage($user, $brand->workspace);
    }

    public function delete(User $user, Brand $brand): bool
    {
        return $this->canManage($user, $brand->workspace);
    }

    private function canManage(User $user, Workspace $workspace): bool
    {
        $member = $workspace->members()->where('user_id', $user->id)->first();

        if (! $member) {
            return false;
        }

        return in_array(Role::tryFrom($member->pivot->role), [Role::Owner, Role::Admin]);
    }
}
