<?php

declare(strict_types=1);

use App\Ai\PlatformRules\Contract;
use App\Ai\PlatformRules\Registry;
use App\Ai\PlatformRules\XRules;
use App\Enums\SocialAccount\Platform;

test('registry returns rule instance for every supported platform', function (Platform $platform) {
    $rules = Registry::for($platform);

    expect($rules)
        ->toBeInstanceOf(Contract::class)
        ->and(strlen($rules->summary()))->toBeGreaterThan(30)
        ->and($rules->specs())->toBeArray();
})->with([
    [Platform::Instagram],
    [Platform::InstagramFacebook],
    [Platform::Facebook],
    [Platform::X],
    [Platform::TikTok],
    [Platform::YouTube],
    [Platform::LinkedIn],
    [Platform::LinkedInPage],
    [Platform::Threads],
    [Platform::Pinterest],
    [Platform::Bluesky],
    [Platform::Mastodon],
]);

test('forMany returns rules for the given platforms in order', function () {
    $rules = Registry::forMany([Platform::X, Platform::Instagram, Platform::TikTok]);

    expect($rules)->toHaveCount(3);
    expect($rules[0]->platform())->toBe(Platform::X);
    expect($rules[1]->platform())->toBe(Platform::Instagram);
    expect($rules[2]->platform())->toBe(Platform::TikTok);
});

test('forMany skips platforms with no registered rules', function () {
    Registry::clear();
    Registry::register(Platform::X, XRules::class);

    $rules = Registry::forMany([Platform::X, Platform::Instagram]);

    expect($rules)->toHaveCount(1);
    expect($rules[0]->platform())->toBe(Platform::X);
});

test('X rules summary mentions 280 char limit', function () {
    expect(Registry::for(Platform::X)->summary())->toContain('280');
});

test('Instagram rules summary mentions 9:16 for Reel', function () {
    expect(Registry::for(Platform::Instagram)->summary())->toContain('9:16');
});

test('TikTok rules specs indicate video only', function () {
    expect(Registry::for(Platform::TikTok)->specs()['video_only'])->toBeTrue();
});
