<?php

namespace Database\Factories;

use App\Enums\UserWorkspace\Role;
use App\Enums\WorkspaceInvite\Status as InviteStatus;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WorkspaceInvite>
 */
class WorkspaceInviteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'invited_by' => User::factory(),
            'email' => fake()->unique()->safeEmail(),
            'token' => Str::random(64),
            'role' => Role::Member,
            'status' => InviteStatus::Pending,
        ];
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => InviteStatus::Accepted,
            'accepted_at' => now(),
        ]);
    }
}
