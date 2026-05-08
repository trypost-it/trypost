<?php

declare(strict_types=1);

use App\Enums\Workspace\ImageStyle;

test('enum exposes the curated set of image styles', function () {
    expect(ImageStyle::values())->toBe([
        'cinematic',
        'illustration',
        'isometric_3d',
        'cartoon',
        'typographic',
        'infographic',
        'minimalist',
        'mockup',
    ]);
});

test('default style is cinematic', function () {
    expect(ImageStyle::DEFAULT)->toBe(ImageStyle::Cinematic);
    expect(ImageStyle::DEFAULT->value)->toBe('cinematic');
});
