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
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
            'total_tokens' => 0,
            'credits' => 0,
        ];
    }

    public function template(): static
    {
        return $this->state(fn () => [
            'type' => UsageType::Template,
            'provider' => 'internal',
        ]);
    }

    public function text(int $credits = 10): static
    {
        $tokens = $credits * 150;

        return $this->state(fn () => [
            'type' => UsageType::Text,
            'provider' => 'openai',
            'model' => 'gpt-5.4',
            'prompt_tokens' => intdiv($tokens, 2),
            'completion_tokens' => intdiv($tokens, 2),
            'total_tokens' => $tokens,
            'credits' => $credits,
        ]);
    }
}
