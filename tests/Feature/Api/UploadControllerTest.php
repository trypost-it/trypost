<?php

declare(strict_types=1);

use App\Enums\UserWorkspace\Role;
use App\Models\Media;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

beforeEach(function () {
    Storage::fake();
    Cache::flush();

    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Member->value]);
});

function signedUploadUrl(Workspace $ws, string $token, ?int $expiresInMinutes = 15): string
{
    return URL::temporarySignedRoute(
        'api.uploads.store',
        now()->addMinutes($expiresInMinutes),
        ['token' => $token, 'ws' => $ws->id],
    );
}

test('valid signed POST stores Media with upload_token', function () {
    $token = (string) Str::uuid();
    $file = UploadedFile::fake()->image('shot.png', 50, 50);

    $response = $this->post(signedUploadUrl($this->workspace, $token), [
        'media' => $file,
    ]);

    $media = Media::where('upload_token', $token)->first();
    expect($media)->not->toBeNull();
    expect($media->mediable_id)->toBe($this->workspace->id);
    expect($media->mediable_type)->toBe('workspace');

    $response->assertCreated()
        ->assertJson([
            'upload_token' => $token,
            'media_id' => $media->id,
            'mime_type' => $media->mime_type,
            'original_filename' => $media->original_filename,
        ]);
});

test('rejects unsigned request', function () {
    $token = (string) Str::uuid();
    $file = UploadedFile::fake()->image('shot.png', 50, 50);

    $response = $this->postJson(route('api.uploads.store', ['token' => $token, 'ws' => $this->workspace->id]), [
        'media' => $file,
    ]);

    $response->assertForbidden();
    expect(Media::where('upload_token', $token)->exists())->toBeFalse();
});

test('rejects tampered workspace_id', function () {
    $other = Workspace::factory()->create();
    $token = (string) Str::uuid();
    $file = UploadedFile::fake()->image('shot.png', 50, 50);

    $url = signedUploadUrl($this->workspace, $token);
    $tampered = str_replace("ws={$this->workspace->id}", "ws={$other->id}", $url);

    $response = $this->postJson($tampered, ['media' => $file]);

    $response->assertForbidden();
});

test('rejects expired URL', function () {
    $token = (string) Str::uuid();
    $file = UploadedFile::fake()->image('shot.png', 50, 50);

    $url = URL::temporarySignedRoute(
        'api.uploads.store',
        now()->subMinute(),
        ['token' => $token, 'ws' => $this->workspace->id],
    );

    $response = $this->postJson($url, ['media' => $file]);
    $response->assertForbidden();
});

test('rejects replay of an already-used token', function () {
    $token = (string) Str::uuid();
    $file1 = UploadedFile::fake()->image('one.png', 50, 50);
    $file2 = UploadedFile::fake()->image('two.png', 50, 50);

    $this->post(signedUploadUrl($this->workspace, $token), ['media' => $file1])->assertCreated();
    $this->postJson(signedUploadUrl($this->workspace, $token), ['media' => $file2])->assertStatus(409);

    expect(Media::where('upload_token', $token)->count())->toBe(1);
});

test('rejects file larger than 50MB', function () {
    $token = (string) Str::uuid();
    $file = UploadedFile::fake()->create('huge.mp4', 51 * 1024 + 1, 'video/mp4');

    $response = $this->postJson(signedUploadUrl($this->workspace, $token), ['media' => $file]);

    $response->assertStatus(422);
    expect(Media::where('upload_token', $token)->exists())->toBeFalse();
});

test('rejects disallowed mime type', function () {
    $token = (string) Str::uuid();
    $file = UploadedFile::fake()->create('evil.exe', 10, 'application/octet-stream');

    $response = $this->postJson(signedUploadUrl($this->workspace, $token), ['media' => $file]);

    $response->assertStatus(422);
    expect(Media::where('upload_token', $token)->exists())->toBeFalse();
});
