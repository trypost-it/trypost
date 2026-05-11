<?php

declare(strict_types=1);

namespace App\Ai\PlatformRules;

use App\Enums\SocialAccount\Platform;

class FacebookRules implements Contract
{
    public function platform(): Platform
    {
        return Platform::Facebook;
    }

    public function specs(): array
    {
        return [
            'max_content_length' => 10000,
            'max_images' => 10,
            'text_only_allowed' => true,
            'formats' => [
                'post' => ['max_media' => 10, 'text_only_allowed' => true],
                'reel' => ['aspect_ratio' => '9:16', 'video_only' => true, 'max_duration_seconds' => 90],
                'story' => ['aspect_ratio' => '9:16', 'ephemeral' => true],
            ],
        ];
    }

    public function summary(): string
    {
        return <<<'TXT'
Facebook: caption up to 10k chars but short posts perform better. Text-only allowed.
- Post: flexible aspect ratio, up to 10 images/videos.
- Reel: vertical 9:16 video only, up to 90s.
- Story: vertical 9:16, single image/video, ephemeral.
TXT;
    }
}
