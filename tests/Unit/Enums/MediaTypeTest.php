<?php

use App\Enums\Media\Type;

test('media type has correct values', function () {
    expect(Type::Image->value)->toBe('image');
    expect(Type::Video->value)->toBe('video');
    expect(Type::Document->value)->toBe('document');
});

test('media type has labels', function () {
    expect(Type::Image->label())->toBe('Imagem');
    expect(Type::Video->label())->toBe('Vídeo');
    expect(Type::Document->label())->toBe('Documento');
});

test('media type has allowed mime types', function () {
    expect(Type::Image->allowedMimeTypes())->toContain('image/jpeg', 'image/png');
    expect(Type::Video->allowedMimeTypes())->toContain('video/mp4', 'video/quicktime');
    expect(Type::Document->allowedMimeTypes())->toContain('application/pdf');
});

test('media type has max size in mb', function () {
    expect(Type::Image->maxSizeInMb())->toBe(10);
    expect(Type::Video->maxSizeInMb())->toBe(2048);
    expect(Type::Document->maxSizeInMb())->toBe(100);
});
