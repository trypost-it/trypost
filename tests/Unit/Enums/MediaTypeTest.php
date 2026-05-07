<?php

declare(strict_types=1);

use App\Enums\Media\Type;

test('media type has correct values', function () {
    expect(Type::Image->value)->toBe('image');
    expect(Type::Video->value)->toBe('video');
});

test('media type has labels', function () {
    expect(Type::Image->label())->toBe('Imagem');
    expect(Type::Video->label())->toBe('Vídeo');
});

test('media type has allowed mime types', function () {
    expect(Type::Image->allowedMimeTypes())->toContain('image/jpeg', 'image/png');
    expect(Type::Video->allowedMimeTypes())->toContain('video/mp4', 'video/quicktime');
});

test('media type max size in mb is read from config', function () {
    config(['postpro.media.max_size_mb.image' => 10]);
    config(['postpro.media.max_size_mb.video' => 1024]);

    expect(Type::Image->maxSizeInMb())->toBe(10);
    expect(Type::Video->maxSizeInMb())->toBe(1024);
});

test('media type exposes derived size units', function () {
    config(['postpro.media.max_size_mb.video' => 1024]);

    expect(Type::Video->maxSizeInKb())->toBe(1024 * 1024);
    expect(Type::Video->maxSizeInBytes())->toBe(1024 * 1024 * 1024);
});

test('media type resolves from mime', function () {
    expect(Type::fromMime('image/jpeg'))->toBe(Type::Image);
    expect(Type::fromMime('video/mp4'))->toBe(Type::Video);
    expect(Type::fromMime('application/pdf'))->toBeNull();
    expect(Type::fromMime('not-a-mime'))->toBeNull();
});

