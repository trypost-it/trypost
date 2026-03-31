<?php

declare(strict_types=1);

namespace App\Actions\Workspace;

use App\Enums\UserWorkspace\Role;
use App\Models\User;
use App\Models\Workspace;

class CreateWorkspace
{
    public static function execute(User $user, array $data): Workspace
    {
        $workspace = Workspace::create([
            'user_id' => $user->id,
            ...$data,
            'timezone' => config('app.timezone', 'UTC'),
        ]);

        $workspace->members()->attach($user->id, ['role' => Role::Owner->value]);
        $user->switchWorkspace($workspace);

        if ($user->hasActiveSubscription()) {
            $user->incrementWorkspaceQuantity();
        }

        return $workspace;
    }
}
