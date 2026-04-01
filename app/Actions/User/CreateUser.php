<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Enums\User\Setup;
use App\Enums\UserWorkspace\Role;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

class CreateUser
{
    /**
     * @param  array{name: string, email: string, password?: string, timezone?: string, setup?: Setup, email_verified_at?: \DateTimeInterface|null}  $data
     */
    public static function execute(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $isInviteRegistration = data_get($data, 'is_invite', false);

            $user = User::create([
                'name' => data_get($data, 'name'),
                'email' => data_get($data, 'email'),
                'password' => data_get($data, 'password'),
                'setup' => data_get($data, 'setup', $isInviteRegistration ? Setup::Completed : Setup::Role),
                'email_verified_at' => data_get($data, 'email_verified_at', $isInviteRegistration ? now() : null),
            ]);

            $workspace = Workspace::create([
                'user_id' => $user->id,
                'name' => $user->name."'s Workspace",
                'timezone' => data_get($data, 'timezone', 'UTC'),
            ]);

            $workspace->members()->attach($user->id, ['role' => Role::Owner->value]);

            $user->update(['current_workspace_id' => $workspace->id]);

            return $user;
        });
    }
}
