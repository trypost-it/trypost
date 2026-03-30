<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UserWorkspace\Role;
use App\Models\Workspace;
use App\Models\WorkspaceInvite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkspaceInvite>
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
