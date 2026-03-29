<?php

namespace Database\Factories;

use App\Models\Workspace;
use App\Models\WorkspaceHashtag;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkspaceHashtag>
 */
class WorkspaceHashtagFactory extends Factory
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
            'name' => fake()->words(2, true),
            'hashtags' => '#'.implode(' #', fake()->words(5)),
        ];
    }
}
