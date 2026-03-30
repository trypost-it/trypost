<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ApiToken;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<ApiToken>
 */
class ApiTokenFactory extends Factory
{
    /**
     * The plain token generated during creation.
     */
    public static ?string $lastPlainToken = null;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $plainToken = 'tp_'.Str::random(48);
        static::$lastPlainToken = $plainToken;

        return [
            'workspace_id' => Workspace::factory(),
            'name' => fake()->words(2, true),
            'token_lookup' => substr($plainToken, 3, 16),
            'token_hash' => Hash::make($plainToken),
            'last_used_at' => null,
            'expires_at' => null,
        ];
    }

    /**
     * Mark the token as expired.
     */
    public function expired(): static
    {
        return $this->state(fn () => [
            'expires_at' => now()->subDay(),
        ]);
    }
}
