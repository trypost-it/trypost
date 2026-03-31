# Image & Media Resize Per Platform

## Problem

Each social platform has different limits for image size, resolution, format, and aspect ratio. Currently we upload media as-is — if it exceeds a platform's limits, the API rejects it. We need to automatically resize/convert images before publishing.

## Solution

Install `intervention/image` and create a `MediaOptimizer` service that processes images per platform before upload. Each platform has a configuration defining its limits, and the optimizer ensures media meets them.

## Library

[Intervention Image v4](https://image.intervention.io/v4) — PHP image handling library supporting GD and Imagick drivers.

## Platform Media Specifications (from official docs)

### Images

| Platform | Max Size | Formats | Max Resolution | Aspect Ratio | Source |
|---|---|---|---|---|---|
| **Instagram** | 8 MB | JPEG only | 1440px width, min 320px | 4:5 to 1.91:1 | [Official](https://developers.facebook.com/docs/instagram-platform/instagram-graph-api/reference/ig-user/media) |
| **Facebook** | 4 MB (PNG: 1 MB rec.) | JPEG, PNG, BMP, GIF, TIFF | Auto-resized | No limit | [Official](https://developers.facebook.com/docs/graph-api/reference/page/photos/) |
| **X/Twitter** | 5 MB | JPG, PNG, GIF, WEBP | No hard limit | No limit | [Official](https://docs.x.com/x-api/media/quickstart/best-practices) |
| **TikTok** | 20 MB | JPEG, WebP | 1080px max | No limit | [Official](https://developers.tiktok.com/doc/content-posting-api-media-transfer-guide) |
| **YouTube** | N/A | N/A | N/A | N/A | Video only |
| **LinkedIn** | < 36M pixels | JPG, GIF, PNG | < 36,152,320 pixels total | No limit | [Official](https://learn.microsoft.com/en-us/linkedin/marketing/community-management/shares/images-api) |
| **Threads** | 8 MB | JPEG only | Same as Instagram | Same as Instagram | Same API as Instagram |
| **Pinterest** | 20 MB (desktop), 32 MB (app) | PNG, JPEG | 1000x1500 recommended | 2:3 recommended | [Official](https://help.pinterest.com/en/business/article/pinterest-product-specs) |
| **Bluesky** | 1 MB | Any | No hard limit | No limit | [Official](https://docs.bsky.app/docs/advanced-guides/posts) |
| **Mastodon** | Instance-dependent (~10 MB) | JPG, PNG, GIF, WebP | No hard limit | No limit | [Official](https://docs.joinmastodon.org/methods/statuses/) |

### Videos

| Platform | Max Size | Formats | Codec | Max Resolution | Duration | Aspect Ratio | Source |
|---|---|---|---|---|---|---|---|
| **Instagram Feed** | 100 MB | MP4, MOV | H.264/HEVC | 1920px | 3s-60min | 4:5 to 1.91:1 | [Official](https://developers.facebook.com/docs/instagram-platform/instagram-graph-api/reference/ig-user/media) |
| **Instagram Reel** | 300 MB | MP4, MOV | H.264/HEVC | 1920px | 3s-15min | 9:16 rec. | [Official](https://developers.facebook.com/docs/instagram-platform/instagram-graph-api/reference/ig-user/media) |
| **Instagram Story** | 100 MB | MP4, MOV | H.264/HEVC | 1920px | 3-60s | 9:16 rec. | [Official](https://developers.facebook.com/docs/instagram-platform/instagram-graph-api/reference/ig-user/media) |
| **Facebook** | 2 GB | MP4 | H.264 | No limit | 1s-40min | No limit | [Official](https://developers.facebook.com/docs/video-api/reference/error-codes/) |
| **X/Twitter** | 512 MB | MP4 | H.264 High | 1280x1024 | 0.5-140s | 1:3 to 3:1 | [Official](https://docs.x.com/x-api/media/quickstart/best-practices) |
| **TikTok** | 4 GB | MP4, WebM, MOV | H.264/H.265/VP8/VP9 | 4096px | Up to 10min | No limit | [Official](https://developers.tiktok.com/doc/content-posting-api-media-transfer-guide) |
| **YouTube** | 128 GB | MP4, MOV, AVI, WebM+ | H.264 rec. | No limit | Up to 12h | No limit | [Official](https://developers.google.com/youtube/v3/docs/videos/insert) |
| **LinkedIn** | 500 MB | MP4 | H.264 | No limit | 3s-30min | No limit | [Official](https://learn.microsoft.com/en-us/linkedin/marketing/community-management/shares/videos-api) |
| **Pinterest** | 2 GB | MP4, MOV, M4V | H.264/H.265 | No limit | 4s-15min | 1:2 to 1.91:1 | [Official](https://help.pinterest.com/en/business/article/pinterest-product-specs) |
| **Bluesky** | 50 MB | MP4 | H.264 | 1920px | Up to 60s | No limit | [Official](https://docs.bsky.app/docs/advanced-guides/posts) |
| **Mastodon** | Instance-dependent (~40 MB) | MP4, WebM | H.264/VP9 | No limit | No limit | No limit | [Official](https://docs.joinmastodon.org/methods/statuses/) |

## Architecture

### MediaOptimizer Service

```php
<?php

namespace App\Services\Media;

use App\Enums\SocialAccount\Platform;
use Intervention\Image\ImageManager;

class MediaOptimizer
{
    private ImageManager $manager;

    public function __construct()
    {
        $this->manager = ImageManager::gd(); // or ::imagick()
    }

    /**
     * Optimize an image for a specific platform.
     * Returns path to optimized temp file (caller must clean up).
     */
    public function optimizeImage(string $filePath, Platform $platform): string
    {
        $config = $this->getImageConfig($platform);
        $image = $this->manager->read($filePath);

        // Resize if needed (maintain aspect ratio)
        if ($config['max_width'] && $image->width() > $config['max_width']) {
            $image->scaleDown(width: $config['max_width']);
        }

        // Convert format if needed
        $tempFile = tempnam(sys_get_temp_dir(), 'media_opt_');
        $encoded = $image->encodeByMediaType($config['format'], quality: $config['quality']);
        file_put_contents($tempFile, $encoded);

        // Check file size, reduce quality if still too large
        while (filesize($tempFile) > $config['max_size'] && $config['quality'] > 30) {
            $config['quality'] -= 10;
            $encoded = $image->encodeByMediaType($config['format'], quality: $config['quality']);
            file_put_contents($tempFile, $encoded);
        }

        return $tempFile;
    }

    private function getImageConfig(Platform $platform): array
    {
        return match ($platform) {
            Platform::Instagram, Platform::Threads => [
                'max_width' => 1440,
                'max_size' => 8 * 1024 * 1024,    // 8 MB
                'format' => 'image/jpeg',
                'quality' => 90,
            ],
            Platform::Facebook => [
                'max_width' => 2048,
                'max_size' => 4 * 1024 * 1024,    // 4 MB
                'format' => 'image/jpeg',
                'quality' => 90,
            ],
            Platform::X => [
                'max_width' => 2048,
                'max_size' => 5 * 1024 * 1024,    // 5 MB
                'format' => 'image/jpeg',
                'quality' => 90,
            ],
            Platform::TikTok => [
                'max_width' => 1080,
                'max_size' => 20 * 1024 * 1024,   // 20 MB
                'format' => 'image/jpeg',
                'quality' => 95,
            ],
            Platform::LinkedIn, Platform::LinkedInPage => [
                'max_width' => 2048,
                'max_size' => 10 * 1024 * 1024,   // 10 MB (practical limit)
                'format' => 'image/jpeg',
                'quality' => 90,
            ],
            Platform::Pinterest => [
                'max_width' => 1000,
                'max_size' => 20 * 1024 * 1024,   // 20 MB
                'format' => 'image/jpeg',
                'quality' => 90,
            ],
            Platform::Bluesky => [
                'max_width' => 2048,
                'max_size' => 976 * 1024,          // ~976 KB (under 1 MB with margin)
                'format' => 'image/jpeg',
                'quality' => 85,
            ],
            Platform::Mastodon => [
                'max_width' => 2048,
                'max_size' => 10 * 1024 * 1024,   // 10 MB
                'format' => 'image/jpeg',
                'quality' => 90,
            ],
            Platform::YouTube => [
                'max_width' => 1920,
                'max_size' => 2 * 1024 * 1024,    // 2 MB (thumbnails only)
                'format' => 'image/jpeg',
                'quality' => 90,
            ],
        };
    }
}
```

### Integration with Publishers

Each publisher calls `MediaOptimizer::optimizeImage()` before uploading images:

```php
// In publisher (e.g., BlueskyPublisher):
$optimizer = app(MediaOptimizer::class);
$optimizedPath = $optimizer->optimizeImage($tempFile, Platform::Bluesky);

try {
    // upload $optimizedPath
} finally {
    @unlink($optimizedPath);
}
```

### What we DON'T do (video transcoding)

Video transcoding (converting codecs, changing resolution) requires FFmpeg and is computationally expensive. For now:
- We validate video format/size before upload
- We let the platform API reject incompatible videos with clear error messages (from the error mapping spec)
- Video transcoding is a future feature if needed

## Testing

- Unit tests for `MediaOptimizer` with sample images of different sizes/formats
- Verify resize maintains aspect ratio
- Verify quality reduction loop stops at threshold
- Verify format conversion (PNG → JPEG)
- Verify Bluesky always produces < 1 MB output

## Files Changed

- Install: `intervention/image` v4 via composer
- Create: `app/Services/Media/MediaOptimizer.php`
- Modify: Publishers that upload images directly (Bluesky, X, LinkedIn, LinkedInPage, Mastodon, Pinterest)
- Create: `tests/Unit/Services/Media/MediaOptimizerTest.php`
