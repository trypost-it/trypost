<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Plan\Slug;
use App\Models\Account;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'plan_id' => fn () => Plan::where('slug', Slug::Plus)->value('id'),
        ];
    }
}
