<?php

declare(strict_types=1);

namespace App\Ai\PlatformRules;

use App\Enums\SocialAccount\Platform;

class XRules implements Contract
{
    public function platform(): Platform
    {
        return Platform::X;
    }

    public function specs(): array
    {
        return [
            'max_content_length' => 280,
            'max_images' => 4,
            'max_videos' => 1,
            'text_only_allowed' => true,
            'best_aspect_ratio' => '16:9',
        ];
    }

    public function summary(): string
    {
        return 'X (Twitter): 280 chars max. Up to 4 images OR 1 video. 16:9 landscape performs best. Concise, punchy copy.';
    }
}
