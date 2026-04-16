<?php

declare(strict_types=1);

namespace App\Ai\PlatformRules;

use App\Enums\SocialAccount\Platform;

class InstagramRules implements Contract
{
    public function platform(): Platform
    {
        return Platform::Instagram;
    }

    public function specs(): array
    {
        return [
            'max_content_length' => 2200,
            'max_images' => 10,
            'max_videos' => 10,
            'text_only_allowed' => false,
            'formats' => [
                'feed' => ['aspect_ratio' => '1:1 or 4:5', 'media_limit' => 10],
                'carousel' => ['aspect_ratio' => '1:1 or 4:5', 'media_limit' => 10],
                'reel' => ['aspect_ratio' => '9:16', 'video_only' => true, 'max_duration_seconds' => 90],
                'story' => ['aspect_ratio' => '9:16', 'media_limit' => 1, 'ephemeral' => true],
            ],
        ];
    }

    public function summary(): string
    {
        return <<<'TXT'
Instagram: caption max 2200 chars, up to 30 hashtags (3-5 recommended).
- Feed: square (1:1) or portrait (4:5). Up to 10 images/videos.
- Carousel: 2-10 slides, all same aspect ratio. First slide is the hook.
- Reel: vertical 9:16 video only, up to 90s (30-60s performs best). Hook in first 1-3s.
- Story: vertical 9:16, single image/video, ephemeral (24h).
TXT;
    }
}
