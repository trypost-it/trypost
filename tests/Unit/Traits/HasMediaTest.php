<?php

use App\Models\Media;
use App\Models\PostPlatform;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake();
});

test('model can get media relationship', function () {
    $workspace = Workspace::factory()->create();

    expect($workspace->media())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\MorphMany::class);
});

test('model can add media from uploaded file', function () {
    $workspace = Workspace::factory()->create();
    $file = UploadedFile::fake()->image('logo.jpg', 100, 100);

    $media = $workspace->addMedia($file, 'logo');

    expect($media)->toBeInstanceOf(Media::class);
    expect($media->collection)->toBe('logo');
    expect($media->mime_type)->toBe('image/jpeg');
    expect($media->original_filename)->toBe('logo.jpg');
    Storage::assertExists($media->path);
});

test('model can get media by collection after adding', function () {
    $workspace = Workspace::factory()->create();
    $file = UploadedFile::fake()->image('logo.jpg', 100, 100);
    $workspace->addMedia($file, 'logo');

    expect($workspace->getMedia('logo')->count())->toBe(1);
});

test('model can get first media from collection', function () {
    $workspace = Workspace::factory()->create();
    $file = UploadedFile::fake()->image('logo.jpg', 100, 100);
    $media = $workspace->addMedia($file, 'logo');

    expect($workspace->getFirstMedia('logo')->id)->toBe($media->id);
});

test('get first media returns null when no media exists', function () {
    $workspace = Workspace::factory()->create();

    expect($workspace->getFirstMedia('logo'))->toBeNull();
});

test('model can get first media url', function () {
    $workspace = Workspace::factory()->create();
    $file = UploadedFile::fake()->image('logo.jpg', 100, 100);
    $workspace->addMedia($file, 'logo');

    $url = $workspace->getFirstMediaUrl('logo');

    expect($url)->not->toBeNull();
});

test('get first media url returns default when no media exists', function () {
    $workspace = Workspace::factory()->create();

    expect($workspace->getFirstMediaUrl('logo', 'default-url'))->toBe('default-url');
});

test('get fallback avatar url returns dicebear url', function () {
    $user = User::factory()->create(['name' => 'John Doe']);

    $url = $user->getFallbackAvatarUrl('John Doe');

    expect($url)->toContain('api.dicebear.com');
    expect($url)->toContain('John+Doe');
});

test('adding media to single collection clears existing media', function () {
    $workspace = Workspace::factory()->create();
    $file1 = UploadedFile::fake()->image('logo1.jpg', 100, 100);
    $file2 = UploadedFile::fake()->image('logo2.jpg', 100, 100);

    $media1 = $workspace->addMedia($file1, 'logo');
    $media2 = $workspace->addMedia($file2, 'logo');

    expect($workspace->getMedia('logo')->count())->toBe(1);
    expect($workspace->getFirstMedia('logo')->id)->toBe($media2->id);
    expect(Media::find($media1->id))->toBeNull();
});

test('adding media to multiple collection does not clear existing', function () {
    $post = PostPlatform::factory()->create();
    $file1 = UploadedFile::fake()->image('image1.jpg', 100, 100);
    $file2 = UploadedFile::fake()->image('image2.jpg', 100, 100);

    $post->addMedia($file1, 'default');
    $post->addMedia($file2, 'default');

    expect($post->getMedia('default')->count())->toBe(2);
});

test('model can add media from file path', function () {
    $workspace = Workspace::factory()->create();

    $tempFile = tempnam(sys_get_temp_dir(), 'test');
    file_put_contents($tempFile, 'fake image content');

    $media = $workspace->addMediaFromPath($tempFile, 'uploaded.jpg', 'logo');

    expect($media)->toBeInstanceOf(Media::class);
    expect($media->original_filename)->toBe('uploaded.jpg');
    Storage::assertExists($media->path);

    unlink($tempFile);
});

test('model can clear media collection', function () {
    $workspace = Workspace::factory()->create();
    $file1 = UploadedFile::fake()->image('logo1.jpg', 100, 100);
    $file2 = UploadedFile::fake()->image('logo2.jpg', 100, 100);
    $file3 = UploadedFile::fake()->image('logo3.jpg', 100, 100);

    // Add to 'logo' collection (single, will only keep last one)
    $workspace->addMedia($file1, 'logo');

    // Need to use a 'multiple' collection model
    $post = PostPlatform::factory()->create();
    $post->addMedia($file2, 'default');
    $post->addMedia($file3, 'default');

    expect($post->getMedia('default')->count())->toBe(2);

    $post->clearMediaCollection('default');

    expect($post->getMedia('default')->count())->toBe(0);
});

test('is single media collection returns true for single collections', function () {
    $workspace = Workspace::factory()->create();

    expect($workspace->isSingleMediaCollection('logo'))->toBeTrue();
});

test('is single media collection returns false for multiple collections', function () {
    $post = PostPlatform::factory()->create();

    expect($post->isSingleMediaCollection('default'))->toBeFalse();
});

test('is single media collection returns false for undefined collections', function () {
    $workspace = Workspace::factory()->create();

    expect($workspace->isSingleMediaCollection('undefined'))->toBeFalse();
});

test('add media detects video type', function () {
    $workspace = Workspace::factory()->create();
    $file = UploadedFile::fake()->create('video.mp4', 1000, 'video/mp4');

    $media = $workspace->addMedia($file, 'logo');

    expect($media->type->value)->toBe('video');
});

test('add media detects document type', function () {
    $workspace = Workspace::factory()->create();
    $file = UploadedFile::fake()->create('document.pdf', 1000, 'application/pdf');

    $media = $workspace->addMedia($file, 'logo');

    expect($media->type->value)->toBe('document');
});

test('add media includes custom meta', function () {
    $workspace = Workspace::factory()->create();
    $file = UploadedFile::fake()->image('logo.jpg', 100, 100);

    $media = $workspace->addMedia($file, 'logo', ['custom_key' => 'custom_value']);

    expect($media->meta)->toHaveKey('custom_key');
    expect($media->meta['custom_key'])->toBe('custom_value');
});

test('add media from path detects image dimensions', function () {
    $workspace = Workspace::factory()->create();

    // Create a real image file
    $tempFile = tempnam(sys_get_temp_dir(), 'test');
    $image = imagecreatetruecolor(200, 150);
    imagejpeg($image, $tempFile);
    imagedestroy($image);

    $media = $workspace->addMediaFromPath($tempFile, 'test.jpg', 'logo');

    expect($media->meta)->toHaveKey('width');
    expect($media->meta)->toHaveKey('height');
    expect($media->meta['width'])->toBe(200);
    expect($media->meta['height'])->toBe(150);

    unlink($tempFile);
});

test('user avatar attribute returns fallback when no media', function () {
    $user = User::factory()->create(['name' => 'Test User']);

    $avatar = $user->avatar;

    expect($avatar['url'])->toContain('dicebear');
    expect($avatar['media_id'])->toBeNull();
});

test('user avatar attribute returns media url when exists', function () {
    $user = User::factory()->create(['name' => 'Test User']);
    $file = UploadedFile::fake()->image('avatar.jpg', 100, 100);
    $media = $user->addMedia($file, 'avatar');

    $user->refresh();
    $avatar = $user->avatar;

    expect($avatar['media_id'])->toBe($media->id);
});
