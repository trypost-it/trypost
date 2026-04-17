<?php

declare(strict_types=1);

namespace App\Actions\Workspace;

use App\Enums\UserWorkspace\Role;
use App\Models\User;
use App\Models\Workspace;

class CreateWorkspace
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function execute(User $user, array $data): Workspace
    {
        $attributes = array_filter([
            'name' => data_get($data, 'name'),
            'brand_website' => data_get($data, 'brand_website'),
            'brand_description' => data_get($data, 'brand_description'),
            'brand_tone' => data_get($data, 'brand_tone'),
            'brand_voice_notes' => data_get($data, 'brand_voice_notes'),
            'content_language' => data_get($data, 'content_language'),
        ], static fn ($value): bool => $value !== null);

        $workspace = Workspace::create([
            ...$attributes,
            'account_id' => $user->account_id,
            'user_id' => $user->id,
        ]);

        $workspace->members()->attach($user->id, ['role' => Role::Member->value]);
        $user->switchWorkspace($workspace);

        return $workspace;
    }
}
