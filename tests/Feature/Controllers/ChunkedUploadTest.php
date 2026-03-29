<?php

use App\Enums\User\Setup;
use App\Models\Media;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake();
    $this->user = User::factory()->create([
        'setup' => Setup::Completed,
    ]);
    $this->user->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_123',
        'stripe_status' => 'active',
        'stripe_price' => 'price_123',
        'quantity' => 1,
    ]);
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);
});

test('chunked upload completes with single chunk', function () {
    $content = str_repeat('x', 1000);

    $response = $this->actingAs($this->user)->call(
        'POST',
        route('app.medias.store-chunked'),
        [],
        [],
        [],
        [
            'HTTP_CONTENT_RANGE' => 'bytes 0-999/1000',
            'HTTP_X_MODEL' => 'workspace',
            'HTTP_X_MODEL_ID' => $this->workspace->id,
            'HTTP_X_COLLECTION' => 'default',
            'HTTP_X_FILE_NAME' => 'test-video.mp4',
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/octet-stream',
        ],
        $content,
    );

    $response->assertSuccessful();
    $response->assertJson(['done' => true]);
    $response->assertJsonStructure(['done', 'id', 'group_id', 'url', 'type', 'original_filename']);
    expect(Media::count())->toBe(1);
});

test('chunked upload reports progress on intermediate chunks', function () {
    $chunkSize = 500;
    $totalSize = 1000;
    $firstChunk = str_repeat('a', $chunkSize);

    $response = $this->actingAs($this->user)->call(
        'POST',
        route('app.medias.store-chunked'),
        [],
        [],
        [],
        [
            'HTTP_CONTENT_RANGE' => "bytes 0-499/{$totalSize}",
            'HTTP_X_MODEL' => 'workspace',
            'HTTP_X_MODEL_ID' => $this->workspace->id,
            'HTTP_X_COLLECTION' => 'default',
            'HTTP_X_FILE_NAME' => 'test-video.mp4',
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/octet-stream',
        ],
        $firstChunk,
    );

    $response->assertSuccessful();
    $response->assertJson([
        'done' => false,
        'progress' => 50,
    ]);
    expect(Media::count())->toBe(0);
});

test('chunked upload completes with multiple chunks', function () {
    $totalSize = 1000;
    $firstChunk = str_repeat('a', 500);
    $secondChunk = str_repeat('b', 500);

    // First chunk
    $this->actingAs($this->user)->call(
        'POST',
        route('app.medias.store-chunked'),
        [],
        [],
        [],
        [
            'HTTP_CONTENT_RANGE' => "bytes 0-499/{$totalSize}",
            'HTTP_X_MODEL' => 'workspace',
            'HTTP_X_MODEL_ID' => $this->workspace->id,
            'HTTP_X_COLLECTION' => 'default',
            'HTTP_X_FILE_NAME' => 'multi-chunk.mp4',
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/octet-stream',
        ],
        $firstChunk,
    )->assertJson(['done' => false]);

    // Second chunk (last)
    $response = $this->actingAs($this->user)->call(
        'POST',
        route('app.medias.store-chunked'),
        [],
        [],
        [],
        [
            'HTTP_CONTENT_RANGE' => "bytes 500-999/{$totalSize}",
            'HTTP_X_MODEL' => 'workspace',
            'HTTP_X_MODEL_ID' => $this->workspace->id,
            'HTTP_X_COLLECTION' => 'default',
            'HTTP_X_FILE_NAME' => 'multi-chunk.mp4',
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/octet-stream',
        ],
        $secondChunk,
    );

    $response->assertSuccessful();
    $response->assertJson(['done' => true]);
    expect(Media::count())->toBe(1);
});

test('chunked upload fails without content range header', function () {
    $response = $this->actingAs($this->user)->call(
        'POST',
        route('app.medias.store-chunked'),
        [],
        [],
        [],
        [
            'HTTP_X_MODEL' => 'workspace',
            'HTTP_X_MODEL_ID' => $this->workspace->id,
            'HTTP_X_FILE_NAME' => 'test.mp4',
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/octet-stream',
        ],
        'content',
    );

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('content_range');
});

test('chunked upload fails with invalid model type', function () {
    $response = $this->actingAs($this->user)->call(
        'POST',
        route('app.medias.store-chunked'),
        [],
        [],
        [],
        [
            'HTTP_CONTENT_RANGE' => 'bytes 0-999/1000',
            'HTTP_X_MODEL' => 'invalid',
            'HTTP_X_MODEL_ID' => $this->workspace->id,
            'HTTP_X_FILE_NAME' => 'test.mp4',
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/octet-stream',
        ],
        str_repeat('x', 1000),
    );

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('model');
});

test('chunked upload fails without model header', function () {
    $response = $this->actingAs($this->user)->call(
        'POST',
        route('app.medias.store-chunked'),
        [],
        [],
        [],
        [
            'HTTP_CONTENT_RANGE' => 'bytes 0-999/1000',
            'HTTP_X_FILE_NAME' => 'test.mp4',
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/octet-stream',
        ],
        str_repeat('x', 1000),
    );

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('model');
});

test('chunked upload fails without file name header', function () {
    $response = $this->actingAs($this->user)->call(
        'POST',
        route('app.medias.store-chunked'),
        [],
        [],
        [],
        [
            'HTTP_CONTENT_RANGE' => 'bytes 0-999/1000',
            'HTTP_X_MODEL' => 'workspace',
            'HTTP_X_MODEL_ID' => $this->workspace->id,
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/octet-stream',
        ],
        str_repeat('x', 1000),
    );

    // X-File-Name defaults to 'upload' so it should still work
    $response->assertSuccessful();
});

test('chunked upload cleans up temp file after completion', function () {
    $content = str_repeat('x', 1000);
    $identifier = md5('cleanup-test.mp4'.'1000');
    $tempPath = storage_path("app/private/chunks/{$identifier}");

    $this->actingAs($this->user)->call(
        'POST',
        route('app.medias.store-chunked'),
        [],
        [],
        [],
        [
            'HTTP_CONTENT_RANGE' => 'bytes 0-999/1000',
            'HTTP_X_MODEL' => 'workspace',
            'HTTP_X_MODEL_ID' => $this->workspace->id,
            'HTTP_X_COLLECTION' => 'default',
            'HTTP_X_FILE_NAME' => 'cleanup-test.mp4',
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/octet-stream',
        ],
        $content,
    )->assertJson(['done' => true]);

    expect(file_exists($tempPath))->toBeFalse();
});
