<?php

namespace Database\Factories;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WorkspaceLabel>
 */
class WorkspaceLabelFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $colors = ['#FDFD96', '#FFD580', '#FFB3BA', '#FF69B4', '#DDA0DD', '#89CFF0', '#90EE90', '#D2B48C', '#D3D3D3'];

        return [
            'workspace_id' => Workspace::factory(),
            'name' => fake()->word(),
            'color' => fake()->randomElement($colors),
        ];
    }
}
