<?php

declare(strict_types=1);

namespace App\Ai\PlatformRules;

use App\Enums\SocialAccount\Platform;

class BlueskyRules implements Contract
{
    public function platform(): Platform
    {
        return Platform::Bluesky;
    }

    public function specs(): array
    {
        return [
            'max_content_length' => 300,
            'max_images' => 4,
            'max_videos' => 1,
            'text_only_allowed' => true,
        ];
    }

    public function summary(): string
    {
        return 'Bluesky: 300 chars max. Up to 4 images or 1 video. Similar to X but more concise.';
    }
}
