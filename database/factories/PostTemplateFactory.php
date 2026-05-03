<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PostTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PostTemplate>
 */
class PostTemplateFactory extends Factory
{
    protected $model = PostTemplate::class;

    public function definition(): array
    {
        return [
            'name' => fake()->sentence(3),
            'description' => fake()->sentence(),
            'category' => 'product_launch',
            'platform' => 'instagram_carousel',
            'content' => fake()->paragraph(),
            'slides' => null,
            'image_count' => 0,
            'image_keywords' => null,
        ];
    }
}
