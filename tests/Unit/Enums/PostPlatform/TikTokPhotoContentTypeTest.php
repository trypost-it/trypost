<?php

declare(strict_types=1);

use App\Enums\PostPlatform\ContentType;
use App\Enums\SocialAccount\Platform as SocialPlatform;

test('tiktok photo content type has correct platform mapping', function () {
    expect(ContentType::TikTokPhoto->platform())->toBe(SocialPlatform::TikTok);
});

test('tiktok photo content type supports up to 35 images', function () {
    expect(ContentType::TikTokPhoto->maxMediaCount())->toBe(35);
});

test('tiktok photo content type supports images and not video', function () {
    expect(ContentType::TikTokPhoto->supportsImage())->toBeTrue();
    expect(ContentType::TikTokPhoto->supportsVideo())->toBeFalse();
});

test('tiktok photo content type label is human-readable', function () {
    expect(ContentType::TikTokPhoto->label())->toBe('Photo carousel');
});
