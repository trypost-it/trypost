<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Ai\UsageType;
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
            'type' => fake()->randomElement(UsageType::cases()),
            'provider' => fake()->randomElement(['openai', 'gemini', 'internal']),
        ];
    }

    public function image(): static
    {
        return $this->state(fn () => [
            'type' => UsageType::Image,
            'provider' => 'internal',
        ]);
    }

    public function text(): static
    {
        return $this->state(fn () => [
            'type' => UsageType::Text,
            'provider' => 'openai',
        ]);
    }
}
