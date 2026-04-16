<?php

declare(strict_types=1);

namespace App\Ai\PlatformRules;

use App\Enums\SocialAccount\Platform;

class LinkedInRules implements Contract
{
    public function platform(): Platform
    {
        return Platform::LinkedIn;
    }

    public function specs(): array
    {
        return [
            'max_content_length' => 3000,
            'max_images' => 1,
            'max_videos' => 1,
            'text_only_allowed' => true,
            'formats' => [
                'post' => ['max_media' => 1],
                'carousel' => ['max_slides' => 20, 'optimal_slides' => '8-12', 'images_only' => true, 'document_based' => true],
            ],
        ];
    }

    public function summary(): string
    {
        return <<<'TXT'
LinkedIn: 3000 chars max. First 2-3 lines visible before "see more" — make them compelling.
Professional tone. Educational/how-to content performs well.
- Post: 1 image or 1 video.
- Carousel: PDF-based document, up to 20 slides (8-12 optimal), images only.
TXT;
    }
}
