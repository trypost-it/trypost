<?php

declare(strict_types=1);

namespace App\Ai\PlatformRules;

use App\Enums\SocialAccount\Platform;

class YouTubeRules implements Contract
{
    public function platform(): Platform
    {
        return Platform::YouTube;
    }

    public function specs(): array
    {
        return [
            'max_content_length' => 5000,
            'max_videos' => 1,
            'text_only_allowed' => false,
            'aspect_ratio' => '9:16',
            'video_only' => true,
            'max_duration_seconds' => 60,
            'requires_title' => true,
        ];
    }

    public function summary(): string
    {
        return 'YouTube Shorts: vertical 9:16 video only, up to 60s. Hook immediately. Loop-friendly content helps. Description max 5000 chars. Title required.';
    }
}
