<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Enums\SocialAccount\Platform;
use Illuminate\Support\Facades\Log;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class MediaOptimizer
{
    private ImageManager $manager;

    public function __construct()
    {
        $this->manager = new ImageManager(Driver::class);
    }

    /**
     * Optimize an image for a specific platform.
     * Returns path to optimized temp file (caller must clean up).
     */
    public function optimizeImage(string $filePath, Platform $platform): string
    {
        $config = $this->getImageConfig($platform);

        // Check image dimensions to prevent GD memory overflow
        $imageInfo = @getimagesize($filePath);
        if ($imageInfo) {
            $width = $imageInfo[0];
            $height = $imageInfo[1];
            $channels = $imageInfo['channels'] ?? 4;
            $estimatedMemory = $width * $height * $channels * 1.5; // 1.5x safety margin

            // If image would use more than 256MB of RAM, skip optimization and return as-is
            if ($estimatedMemory > 256 * 1024 * 1024) {
                Log::warning('MediaOptimizer: Image too large for GD processing', [
                    'width' => $width,
                    'height' => $height,
                    'estimated_memory' => $estimatedMemory,
                    'platform' => $platform->value,
                ]);

                // Copy original file to temp location and return
                $tempFile = tempnam(sys_get_temp_dir(), 'media_opt_');
                copy($filePath, $tempFile);

                return $tempFile;
            }
        }

        $image = $this->manager->decodePath($filePath);

        $maxWidth = data_get($config, 'max_width');
        $maxSize = data_get($config, 'max_size');
        $format = data_get($config, 'format');
        $quality = data_get($config, 'quality');

        // Resize if needed (maintain aspect ratio, never upscale)
        if ($maxWidth && $image->width() > $maxWidth) {
            $image->scaleDown(width: $maxWidth);
        }

        // Encode to target format
        $tempFile = tempnam(sys_get_temp_dir(), 'media_opt_');
        $encoded = $image->encodeUsingMediaType($format, quality: $quality);
        file_put_contents($tempFile, (string) $encoded);

        // Reduce quality iteratively if file still too large
        while (filesize($tempFile) > $maxSize && $quality > 30) {
            $quality -= 10;
            $encoded = $image->encodeUsingMediaType($format, quality: $quality);
            file_put_contents($tempFile, (string) $encoded);
        }

        return $tempFile;
    }

    /**
     * @return array{max_width: int, max_size: int, format: string, quality: int}
     */
    private function getImageConfig(Platform $platform): array
    {
        return match ($platform) {
            Platform::Instagram, Platform::InstagramFacebook, Platform::Threads => [
                'max_width' => 1440,
                'max_size' => 8 * 1024 * 1024,
                'format' => 'image/jpeg',
                'quality' => 90,
            ],
            Platform::Facebook => [
                'max_width' => 2048,
                'max_size' => 4 * 1024 * 1024,
                'format' => 'image/jpeg',
                'quality' => 90,
            ],
            Platform::X => [
                'max_width' => 2048,
                'max_size' => 5 * 1024 * 1024,
                'format' => 'image/jpeg',
                'quality' => 90,
            ],
            Platform::TikTok => [
                'max_width' => 1080,
                'max_size' => 20 * 1024 * 1024,
                'format' => 'image/jpeg',
                'quality' => 95,
            ],
            Platform::LinkedIn, Platform::LinkedInPage => [
                'max_width' => 2048,
                'max_size' => 10 * 1024 * 1024,
                'format' => 'image/jpeg',
                'quality' => 90,
            ],
            Platform::Pinterest => [
                'max_width' => 1000,
                'max_size' => 20 * 1024 * 1024,
                'format' => 'image/jpeg',
                'quality' => 90,
            ],
            Platform::Bluesky => [
                'max_width' => 2048,
                'max_size' => 976 * 1024,
                'format' => 'image/jpeg',
                'quality' => 85,
            ],
            Platform::Mastodon => [
                'max_width' => 2048,
                'max_size' => 10 * 1024 * 1024,
                'format' => 'image/jpeg',
                'quality' => 90,
            ],
            Platform::YouTube => [
                'max_width' => 1920,
                'max_size' => 2 * 1024 * 1024,
                'format' => 'image/jpeg',
                'quality' => 90,
            ],
        };
    }
}
