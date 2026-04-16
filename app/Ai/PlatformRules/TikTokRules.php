<?php

declare(strict_types=1);

namespace App\Ai\PlatformRules;

use App\Enums\SocialAccount\Platform;

class TikTokRules implements Contract
{
    public function platform(): Platform
    {
        return Platform::TikTok;
    }

    public function specs(): array
    {
        return [
            'max_content_length' => 2200,
            'max_videos' => 1,
            'text_only_allowed' => false,
            'aspect_ratio' => '9:16',
            'video_only' => true,
        ];
    }

    public function summary(): string
    {
        return 'TikTok: vertical 9:16 video only. 15-60s performs best. Hook in first 1-2s. Trendy, authentic style. Caption max 2200 chars.';
    }
}
