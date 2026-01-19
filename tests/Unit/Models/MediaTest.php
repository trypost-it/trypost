<?php

use App\Enums\Media\Type as MediaType;
use App\Models\Media;
use App\Models\Workspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake();
});

test('media belongs to mediable', function () {
    $workspace = Workspace::factory()->create();
    $file = UploadedFile::fake()->image('logo.jpg', 100, 100);
    $media = $workspace->addMedia($file, 'logo');

    expect($media->mediable->id)->toBe($workspace->id);
});

test('media has url attribute', function () {
    $workspace = Workspace::factory()->create();
    $file = UploadedFile::fake()->image('logo.jpg', 100, 100);
    $media = $workspace->addMedia($file, 'logo');

    expect($media->url)->not->toBeEmpty();
});

test('media casts type to enum', function () {
    $workspace = Workspace::factory()->create();
    $file = UploadedFile::fake()->image('logo.jpg', 100, 100);
    $media = $workspace->addMedia($file, 'logo');

    expect($media->type)->toBeInstanceOf(MediaType::class);
});

test('media casts size to integer', function () {
    $workspace = Workspace::factory()->create();
    $file = UploadedFile::fake()->image('logo.jpg', 100, 100);
    $media = $workspace->addMedia($file, 'logo');

    expect($media->size)->toBeInt();
});

test('media casts meta to array', function () {
    $workspace = Workspace::factory()->create();
    $file = UploadedFile::fake()->image('logo.jpg', 100, 100);
    $media = $workspace->addMedia($file, 'logo', ['key' => 'value']);

    expect($media->meta)->toBeArray();
    expect($media->meta['key'])->toBe('value');
});

test('media deletes file from storage when deleted', function () {
    $workspace = Workspace::factory()->create();
    $file = UploadedFile::fake()->image('logo.jpg', 100, 100);
    $media = $workspace->addMedia($file, 'logo');
    $path = $media->path;

    Storage::assertExists($path);

    $media->delete();

    Storage::assertMissing($path);
});

test('media can get temporary url', function () {
    // Use a driver that supports temporary URLs
    Storage::fake('s3');
    config(['filesystems.default' => 's3']);

    $workspace = Workspace::factory()->create();
    $file = UploadedFile::fake()->image('logo.jpg', 100, 100);
    $media = $workspace->addMedia($file, 'logo');

    // The fake driver returns a basic URL format
    $url = $media->getTemporaryUrl(30);

    expect($url)->toBeString();
});
