<?php

use App\Enums\Media\Type as MediaType;
use App\Enums\SocialAccount\Platform;

test('platform has correct labels', function () {
    expect(Platform::LinkedIn->label())->toBe('LinkedIn');
    expect(Platform::LinkedInPage->label())->toBe('LinkedIn Page');
    expect(Platform::X->label())->toBe('X');
    expect(Platform::TikTok->label())->toBe('TikTok');
    expect(Platform::YouTube->label())->toBe('YouTube Shorts');
    expect(Platform::Facebook->label())->toBe('Facebook Page');
    expect(Platform::Instagram->label())->toBe('Instagram');
    expect(Platform::Threads->label())->toBe('Threads');
    expect(Platform::Pinterest->label())->toBe('Pinterest');
    expect(Platform::Bluesky->label())->toBe('Bluesky');
    expect(Platform::Mastodon->label())->toBe('Mastodon');
});

test('platform has correct colors', function () {
    expect(Platform::LinkedIn->color())->toBe('#0A66C2');
    expect(Platform::LinkedInPage->color())->toBe('#0A66C2');
    expect(Platform::X->color())->toBe('#000000');
    expect(Platform::TikTok->color())->toBe('#000000');
    expect(Platform::YouTube->color())->toBe('#FF0000');
    expect(Platform::Facebook->color())->toBe('#1877F2');
    expect(Platform::Instagram->color())->toBe('#E4405F');
    expect(Platform::Threads->color())->toBe('#000000');
    expect(Platform::Pinterest->color())->toBe('#E60023');
    expect(Platform::Bluesky->color())->toBe('#0085FF');
    expect(Platform::Mastodon->color())->toBe('#6364FF');
});

test('platform has correct allowed media types', function () {
    expect(Platform::LinkedIn->allowedMediaTypes())->toContain(MediaType::Image, MediaType::Video, MediaType::Document);
    expect(Platform::X->allowedMediaTypes())->toContain(MediaType::Image, MediaType::Video);
    expect(Platform::TikTok->allowedMediaTypes())->toBe([MediaType::Video]);
    expect(Platform::YouTube->allowedMediaTypes())->toBe([MediaType::Video]);
    expect(Platform::Instagram->allowedMediaTypes())->toContain(MediaType::Image, MediaType::Video);
});

test('platform has correct max images', function () {
    expect(Platform::LinkedIn->maxImages())->toBe(1);
    expect(Platform::X->maxImages())->toBe(4);
    expect(Platform::TikTok->maxImages())->toBe(0);
    expect(Platform::YouTube->maxImages())->toBe(0);
    expect(Platform::Facebook->maxImages())->toBe(10);
    expect(Platform::Instagram->maxImages())->toBe(10);
    expect(Platform::Threads->maxImages())->toBe(10);
    expect(Platform::Pinterest->maxImages())->toBe(5);
    expect(Platform::Bluesky->maxImages())->toBe(4);
    expect(Platform::Mastodon->maxImages())->toBe(4);
});

test('platform has correct max content length', function () {
    expect(Platform::LinkedIn->maxContentLength())->toBe(3000);
    expect(Platform::X->maxContentLength())->toBe(280);
    expect(Platform::TikTok->maxContentLength())->toBe(2200);
    expect(Platform::YouTube->maxContentLength())->toBe(5000);
    expect(Platform::Facebook->maxContentLength())->toBe(63206);
    expect(Platform::Instagram->maxContentLength())->toBe(2200);
    expect(Platform::Threads->maxContentLength())->toBe(500);
    expect(Platform::Pinterest->maxContentLength())->toBe(800);
    expect(Platform::Bluesky->maxContentLength())->toBe(300);
    expect(Platform::Mastodon->maxContentLength())->toBe(500);
});

test('platform supports text only correctly', function () {
    expect(Platform::LinkedIn->supportsTextOnly())->toBeTrue();
    expect(Platform::LinkedInPage->supportsTextOnly())->toBeTrue();
    expect(Platform::X->supportsTextOnly())->toBeTrue();
    expect(Platform::Facebook->supportsTextOnly())->toBeTrue();
    expect(Platform::Threads->supportsTextOnly())->toBeTrue();
    expect(Platform::Bluesky->supportsTextOnly())->toBeTrue();
    expect(Platform::Mastodon->supportsTextOnly())->toBeTrue();

    expect(Platform::TikTok->supportsTextOnly())->toBeFalse();
    expect(Platform::YouTube->supportsTextOnly())->toBeFalse();
    expect(Platform::Instagram->supportsTextOnly())->toBeFalse();
    expect(Platform::Pinterest->supportsTextOnly())->toBeFalse();
});

test('platform is enabled by default', function () {
    expect(Platform::LinkedIn->isEnabled())->toBeTrue();
    expect(Platform::Instagram->isEnabled())->toBeTrue();
});

test('platform can be disabled via config', function () {
    config(['trypost.platforms.linkedin.enabled' => false]);

    expect(Platform::LinkedIn->isEnabled())->toBeFalse();
});

test('can get all enabled platforms', function () {
    $enabled = Platform::enabled();

    expect($enabled)->toBeArray();
    expect(count($enabled))->toBeGreaterThan(0);
});

test('disabled platforms are excluded from enabled list', function () {
    config(['trypost.platforms.linkedin.enabled' => false]);

    $enabled = Platform::enabled();

    expect($enabled)->not->toContain(Platform::LinkedIn);
});
