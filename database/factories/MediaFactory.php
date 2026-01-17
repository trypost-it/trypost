<?php

namespace Database\Factories;

use App\Enums\Media\Type as MediaType;
use App\Models\Media;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Media>
 */
class MediaFactory extends Factory
{
    protected $model = Media::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'collection' => 'default',
            'type' => MediaType::Image,
            'path' => 'media/'.now()->format('Y-m').'/'.$this->faker->uuid().'.jpg',
            'original_filename' => $this->faker->word().'.jpg',
            'mime_type' => 'image/jpeg',
            'size' => $this->faker->numberBetween(10000, 5000000),
            'order' => 0,
            'meta' => [
                'width' => 1920,
                'height' => 1080,
            ],
        ];
    }

    public function video(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => MediaType::Video,
            'path' => 'media/'.now()->format('Y-m').'/'.$this->faker->uuid().'.mp4',
            'original_filename' => $this->faker->word().'.mp4',
            'mime_type' => 'video/mp4',
            'meta' => [
                'duration' => $this->faker->numberBetween(10, 300),
            ],
        ]);
    }

    public function logo(): static
    {
        return $this->state(fn (array $attributes) => [
            'collection' => 'logo',
        ]);
    }

    public function avatar(): static
    {
        return $this->state(fn (array $attributes) => [
            'collection' => 'avatar',
        ]);
    }
}
