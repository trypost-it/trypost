<?php

namespace Database\Factories;

use App\Enums\SocialAccount\Platform;
use App\Enums\SocialAccount\Status;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SocialAccount>
 */
class SocialAccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'platform' => Platform::LinkedIn,
            'platform_user_id' => $this->faker->uuid(),
            'username' => $this->faker->userName(),
            'display_name' => $this->faker->name(),
            'access_token' => $this->faker->sha256(),
            'refresh_token' => $this->faker->sha256(),
            'token_expires_at' => now()->addDays(60),
            'scopes' => [],
            'meta' => [],
            'status' => Status::Connected,
        ];
    }

    public function linkedin(): static
    {
        return $this->state(fn (array $attributes) => [
            'platform' => Platform::LinkedIn,
        ]);
    }

    public function linkedinPage(): static
    {
        return $this->state(fn (array $attributes) => [
            'platform' => Platform::LinkedInPage,
        ]);
    }

    public function x(): static
    {
        return $this->state(fn (array $attributes) => [
            'platform' => Platform::X,
        ]);
    }

    public function tiktok(): static
    {
        return $this->state(fn (array $attributes) => [
            'platform' => Platform::TikTok,
        ]);
    }

    public function youtube(): static
    {
        return $this->state(fn (array $attributes) => [
            'platform' => Platform::YouTube,
        ]);
    }

    public function facebook(): static
    {
        return $this->state(fn (array $attributes) => [
            'platform' => Platform::Facebook,
        ]);
    }

    public function instagram(): static
    {
        return $this->state(fn (array $attributes) => [
            'platform' => Platform::Instagram,
        ]);
    }

    public function threads(): static
    {
        return $this->state(fn (array $attributes) => [
            'platform' => Platform::Threads,
        ]);
    }

    public function pinterest(): static
    {
        return $this->state(fn (array $attributes) => [
            'platform' => Platform::Pinterest,
        ]);
    }

    public function bluesky(): static
    {
        return $this->state(fn (array $attributes) => [
            'platform' => Platform::Bluesky,
            'token_expires_at' => now()->addHours(2),
            'meta' => [
                'service' => 'https://bsky.social',
                'identifier' => 'test@example.com',
                'password' => encrypt('test-app-password'),
            ],
        ]);
    }

    public function mastodon(): static
    {
        return $this->state(fn (array $attributes) => [
            'platform' => Platform::Mastodon,
            'token_expires_at' => null, // Mastodon tokens don't expire
            'meta' => [
                'instance' => 'https://mastodon.social',
                'client_id' => 'test-client-id',
                'client_secret' => 'test-client-secret',
            ],
        ]);
    }

    public function disconnected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Status::Disconnected,
            'error_message' => 'Token expired',
            'disconnected_at' => now(),
        ]);
    }

    public function tokenExpired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Status::TokenExpired,
            'token_expires_at' => now()->subDay(),
        ]);
    }
}
