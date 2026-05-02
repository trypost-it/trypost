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

it('crops a wide image to a 1:1 square', function () use (&$tempFiles) {
    $source = createTestImage(1920, 1080);
    $tempFiles[] = $source;

    $optimizer = new MediaOptimizer;
    $result = $optimizer->cropToAspectRatio($source, 1.0);
    $tempFiles[] = $result;

    $manager = new ImageManager(Driver::class);
    $cropped = $manager->decodePath($result);

    expect($cropped->width())->toBe($cropped->height());
    expect($cropped->height())->toBe(1080);
});

it('crops a tall image to a 4:5 portrait', function () use (&$tempFiles) {
    $source = createTestImage(1000, 2000);
    $tempFiles[] = $source;

    $optimizer = new MediaOptimizer;
    $result = $optimizer->cropToAspectRatio($source, 4 / 5);
    $tempFiles[] = $result;

    $manager = new ImageManager(Driver::class);
    $cropped = $manager->decodePath($result);

    $ratio = $cropped->width() / $cropped->height();
    expect(abs($ratio - 0.8))->toBeLessThan(0.01);
});

it('crops a square image to a 16:9 landscape', function () use (&$tempFiles) {
    $source = createTestImage(1080, 1080);
    $tempFiles[] = $source;

    $optimizer = new MediaOptimizer;
    $result = $optimizer->cropToAspectRatio($source, 16 / 9);
    $tempFiles[] = $result;

    $manager = new ImageManager(Driver::class);
    $cropped = $manager->decodePath($result);

    $ratio = $cropped->width() / $cropped->height();
    expect(abs($ratio - 16 / 9))->toBeLessThan(0.01);
});

it('returns a copy when image already matches the target ratio', function () use (&$tempFiles) {
    $source = createTestImage(800, 800);
    $tempFiles[] = $source;

    $optimizer = new MediaOptimizer;
    $result = $optimizer->cropToAspectRatio($source, 1.0);
    $tempFiles[] = $result;

    $manager = new ImageManager(Driver::class);
    $cropped = $manager->decodePath($result);

    expect($cropped->width())->toBe(800);
    expect($cropped->height())->toBe(800);
});
