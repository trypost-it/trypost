<?php

declare(strict_types=1);

namespace App\Ai\PlatformRules;

use App\Enums\SocialAccount\Platform;

class PinterestRules implements Contract
{
    public function platform(): Platform
    {
        return Platform::Pinterest;
    }

    public function specs(): array
    {
        return [
            'max_content_length' => 800,
            'max_images' => 5,
            'max_videos' => 1,
            'text_only_allowed' => false,
            'formats' => [
                'pin' => ['aspect_ratio' => '2:3', 'media_limit' => 1, 'images_only' => true],
                'video_pin' => ['aspect_ratio' => '9:16 or 2:3', 'video_only' => true, 'min_duration_seconds' => 4, 'max_duration_seconds' => 900],
                'carousel' => ['aspect_ratio' => '2:3', 'max_slides' => 5, 'images_only' => true],
            ],
        ];
    }

    public function summary(): string
    {
        return <<<'TXT'
Pinterest: title max 100 chars, description max 500 chars. Text overlay on images helps discovery.
- Pin: portrait 2:3 (1000x1500px), single image.
- Video Pin: vertical 9:16 or portrait 2:3, 4s to 15 minutes.
- Carousel: portrait 2:3, up to 5 images.
TXT;
    }
}
