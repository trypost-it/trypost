<?php

declare(strict_types=1);

namespace App\Ai\Providers;

use Laravel\Ai\Providers\GeminiProvider;

/**
 * Extends the base Gemini provider with social-media-friendly aspect ratios.
 *
 * The stock provider only exposes 1:1, 2:3, 3:2. Social posts need 9:16
 * (Reels/Stories/TikTok/Shorts) and 16:9 (X/LinkedIn/Facebook video).
 * Gemini's native API supports both, but the SDK's match statement silently
 * drops anything outside its three documented ratios. We re-expose them here.
 */
class ExtendedGeminiProvider extends GeminiProvider
{
    /**
     * @param  'low'|'medium'|'high'|'1K'|'2K'|'4K'|null  $quality
     */
    public function defaultImageOptions(?string $size = null, $quality = null): array
    {
        return array_filter([
            'image_size' => match ($quality) {
                'low', '1K' => '1K',
                'medium', '2K' => '2K',
                'high', '4K' => '4K',
                default => '1K',
            },
            'aspect_ratio' => match ($size) {
                '1:1' => '1:1',
                '2:3' => '2:3',
                '3:2' => '3:2',
                '9:16' => '9:16',
                '16:9' => '16:9',
                '4:3' => '4:3',
                '3:4' => '3:4',
                '4:5' => '4:5',
                '5:4' => '5:4',
                '21:9' => '21:9',
                default => null,
            },
        ]);
    }
}
