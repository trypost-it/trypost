<?php

namespace App\Actions\Fortify;

use App\Concerns\ProfileValidationRules;
use App\Enums\User\Setup;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => ['required', 'string', Password::default()],
        ])->validate();

        return DB::transaction(function () use ($input) {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
                'setup' => Setup::Role,
            ]);

            // Create default workspace for new user
            $workspace = $user->workspaces()->create([
                'name' => 'My Workspace',
                'timezone' => 'UTC',
            ]);

            // Add user as owner member
            $workspace->members()->attach($user->id, ['role' => 'owner']);

            // Set as current workspace
            $user->update(['current_workspace_id' => $workspace->id]);

            return $user;
        });
    }
}
