<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AiMessage;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AiMessage> */
class AiMessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'post_id' => Post::factory(),
            'user_id' => User::factory(),
            'role' => 'user',
            'content' => $this->faker->sentence(),
            'attachments' => [],
        ];
    }

    public function assistant(): static
    {
        return $this->state(fn () => [
            'role' => 'assistant',
            'user_id' => null,
        ]);
    }
}
