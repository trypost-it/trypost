<?php

namespace Database\Factories;

use App\Enums\UserWorkspace\Role;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

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
            'email' => fake()->unique()->safeEmail(),
            'role' => fake()->randomElement(Role::cases()),
            'workspace_id' => Workspace::factory(),
        ];
    }
}
