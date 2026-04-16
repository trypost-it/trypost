<?php

declare(strict_types=1);

namespace App\Ai\PlatformRules;

use App\Enums\SocialAccount\Platform;

class MastodonRules implements Contract
{
    public function platform(): Platform
    {
        return Platform::Mastodon;
    }

    public function specs(): array
    {
        return [
            'max_content_length' => 500,
            'max_images' => 4,
            'max_videos' => 1,
            'text_only_allowed' => true,
        ];
    }

    public function summary(): string
    {
        return 'Mastodon: 500 chars max. Up to 4 images or 1 video. Community-focused, avoid commercial tone.';
    }
}
