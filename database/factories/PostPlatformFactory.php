<?php

namespace Database\Factories;

use App\Enums\PostPlatform\ContentType;
use App\Enums\SocialAccount\Platform;
use App\Models\Post;
use App\Models\SocialAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PostPlatform>
 */
class PostPlatformFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'post_id' => Post::factory(),
            'social_account_id' => SocialAccount::factory(),
            'enabled' => true,
            'platform' => Platform::LinkedIn,
            'content' => $this->faker->paragraph(),
            'content_type' => ContentType::Text,
            'status' => 'pending',
            'meta' => [],
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'enabled' => false,
        ]);
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'published',
            'platform_post_id' => $this->faker->uuid(),
            'platform_url' => $this->faker->url(),
            'published_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'error_message' => 'Failed to publish',
        ]);
    }

    public function linkedin(): static
    {
        return $this->state(fn (array $attributes) => [
            'platform' => Platform::LinkedIn,
        ]);
    }

    public function x(): static
    {
        return $this->state(fn (array $attributes) => [
            'platform' => Platform::X,
        ]);
    }

    public function instagram(): static
    {
        return $this->state(fn (array $attributes) => [
            'platform' => Platform::Instagram,
        ]);
    }
}
