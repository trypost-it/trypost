<?php

declare(strict_types=1);

use App\Enums\SocialAccount\Platform;
use App\Services\Media\MediaOptimizer;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

function createTestImage(int $width, int $height, string $format = 'image/jpeg'): string
{
    $manager = new ImageManager(Driver::class);
    $image = $manager->createImage($width, $height)->fill('cccccc');
    $tempFile = tempnam(sys_get_temp_dir(), 'test_img_');
    $encoded = $image->encodeUsingMediaType($format);
    file_put_contents($tempFile, (string) $encoded);

    return $tempFile;
}

$tempFiles = [];

afterEach(function () use (&$tempFiles) {
    foreach ($tempFiles as $file) {
        @unlink($file);
    }
    $tempFiles = [];
});

it('resizes wide image for instagram', function () use (&$tempFiles) {
    $source = createTestImage(3000, 2000);
    $tempFiles[] = $source;

    $optimizer = new MediaOptimizer;
    $result = $optimizer->optimizeImage($source, Platform::Instagram);
    $tempFiles[] = $result;

    $manager = new ImageManager(Driver::class);
    $optimized = $manager->decodePath($result);

    expect($optimized->width())->toBeLessThanOrEqual(1440);

    $bytes = file_get_contents($result);
    expect(ord($bytes[0]))->toBe(0xFF)
        ->and(ord($bytes[1]))->toBe(0xD8);
});

it('resizes for bluesky under 1mb', function () use (&$tempFiles) {
    $source = createTestImage(2000, 2000);
    $tempFiles[] = $source;

    $optimizer = new MediaOptimizer;
    $result = $optimizer->optimizeImage($source, Platform::Bluesky);
    $tempFiles[] = $result;

    expect(filesize($result))->toBeLessThan(976 * 1024);
});

it('does not upscale small images', function () use (&$tempFiles) {
    $source = createTestImage(500, 500);
    $tempFiles[] = $source;

    $optimizer = new MediaOptimizer;
    $result = $optimizer->optimizeImage($source, Platform::Instagram);
    $tempFiles[] = $result;

    $manager = new ImageManager(Driver::class);
    $optimized = $manager->decodePath($result);

    expect($optimized->width())->toBe(500);
});

it('converts png to jpeg for instagram', function () use (&$tempFiles) {
    $source = createTestImage(800, 600, 'image/png');
    $tempFiles[] = $source;

    $optimizer = new MediaOptimizer;
    $result = $optimizer->optimizeImage($source, Platform::Instagram);
    $tempFiles[] = $result;

    $bytes = file_get_contents($result);
    expect(ord($bytes[0]))->toBe(0xFF)
        ->and(ord($bytes[1]))->toBe(0xD8);
});

it('resizes for tiktok max 1080', function () use (&$tempFiles) {
    $source = createTestImage(2000, 2000);
    $tempFiles[] = $source;

    $optimizer = new MediaOptimizer;
    $result = $optimizer->optimizeImage($source, Platform::TikTok);
    $tempFiles[] = $result;

    $manager = new ImageManager(Driver::class);
    $optimized = $manager->decodePath($result);

    expect($optimized->width())->toBeLessThanOrEqual(1080);
});

it('resizes for pinterest max 1000', function () use (&$tempFiles) {
    $source = createTestImage(2000, 3000);
    $tempFiles[] = $source;

    $optimizer = new MediaOptimizer;
    $result = $optimizer->optimizeImage($source, Platform::Pinterest);
    $tempFiles[] = $result;

    $manager = new ImageManager(Driver::class);
    $optimized = $manager->decodePath($result);

    expect($optimized->width())->toBeLessThanOrEqual(1000);
});

it('handles all platforms without error', function () use (&$tempFiles) {
    $source = createTestImage(1000, 800);
    $tempFiles[] = $source;

    $optimizer = new MediaOptimizer;

    foreach (Platform::cases() as $platform) {
        $result = $optimizer->optimizeImage($source, $platform);
        $tempFiles[] = $result;

        expect(file_exists($result))->toBeTrue()
            ->and(filesize($result))->toBeGreaterThan(0);
    }
});

it('returns valid temp file path', function () use (&$tempFiles) {
    $source = createTestImage(800, 600);
    $tempFiles[] = $source;

    $optimizer = new MediaOptimizer;
    $result = $optimizer->optimizeImage($source, Platform::Facebook);
    $tempFiles[] = $result;

    expect(file_exists($result))->toBeTrue()
        ->and(filesize($result))->toBeGreaterThan(0);
});
