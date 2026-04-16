<?php

declare(strict_types=1);

namespace App\Ai\PlatformRules;

use App\Enums\SocialAccount\Platform;

class ThreadsRules implements Contract
{
    public function platform(): Platform
    {
        return Platform::Threads;
    }

    public function specs(): array
    {
        return [
            'max_content_length' => 500,
            'max_images' => 10,
            'max_videos' => 10,
            'text_only_allowed' => true,
        ];
    }

    public function summary(): string
    {
        return 'Threads: 500 chars max. Up to 10 images/videos. Conversational tone. Ask questions to drive replies.';
    }
}
