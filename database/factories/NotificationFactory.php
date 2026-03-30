<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Notification\Channel;
use App\Enums\Notification\Type;
use App\Models\Notification;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'workspace_id' => Workspace::factory(),
            'type' => fake()->randomElement(Type::cases()),
            'channel' => Channel::InApp,
            'title' => fake()->sentence(4),
            'body' => fake()->sentence(10),
            'data' => null,
            'read_at' => null,
        ];
    }

    public function read(): static
    {
        return $this->state(fn () => ['read_at' => now()]);
    }
}
