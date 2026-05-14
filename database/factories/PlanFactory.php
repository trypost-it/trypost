<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Plan\Slug;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => fake()->randomElement(Slug::cases()),
            'name' => fake()->word(),
            'stripe_monthly_price_id' => null,
            'stripe_yearly_price_id' => null,
            'social_account_limit' => fake()->randomElement([3, 10, 25, 100]),
            'member_limit' => fake()->randomElement([1, 3, 10, 50]),
            'workspace_limit' => fake()->randomElement([1, 3, 10, 50]),
            'monthly_credits_limit' => fake()->randomElement([1000, 2000, 5000, 15000]),
            'allowed_networks' => null,
            'can_use_ai' => true,
            'can_use_analytics' => true,
            'scheduled_posts_limit' => null,
            'sort' => 0,
            'is_archived' => false,
        ];
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_archived' => true,
        ]);
    }
}
