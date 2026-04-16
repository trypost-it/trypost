<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Account;
use App\Models\AiUsageLog;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AiUsageLog> */
class AiUsageLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'workspace_id' => Workspace::factory(),
            'type' => fake()->randomElement(['image', 'video', 'audio']),
            'provider' => fake()->randomElement(['gemini', 'veo', 'elevenlabs']),
        ];
    }

    public function image(): static
    {
        return $this->state(fn () => [
            'type' => 'image',
            'provider' => 'gemini',
        ]);
    }

    public function video(): static
    {
        return $this->state(fn () => [
            'type' => 'video',
            'provider' => 'veo',
        ]);
    }

    public function audio(): static
    {
        return $this->state(fn () => [
            'type' => 'audio',
            'provider' => 'elevenlabs',
        ]);
    }
}
