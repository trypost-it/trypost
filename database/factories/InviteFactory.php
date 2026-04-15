<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Account;
use App\Models\Invite;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invite>
 */
class InviteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'invited_by' => User::factory(),
            'email' => fake()->unique()->safeEmail(),
            'workspaces' => [],
        ];
    }
}
