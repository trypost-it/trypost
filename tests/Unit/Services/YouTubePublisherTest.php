<?php

use App\Enums\PostPlatform\ContentType;
use App\Enums\SocialAccount\Platform;
use App\Exceptions\TokenExpiredException;
use App\Models\Media;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\Workspace;
use App\Services\Social\YouTubePublisher;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->workspace = Workspace::factory()->create();
    $this->socialAccount = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::YouTube,
        'platform_user_id' => 'youtube-channel-123',
        'access_token' => 'test-token',
        'token_expires_at' => now()->addDays(30),
    ]);
    $this->post = Post::factory()->create(['workspace_id' => $this->workspace->id]);
    $this->postPlatform = PostPlatform::factory()->create([
        'post_id' => $this->post->id,
        'social_account_id' => $this->socialAccount->id,
        'content' => 'Test YouTube Short description',
        'content_type' => ContentType::YouTubeShort,
    ]);
});

test('youtube publisher throws exception when no media', function () {
    $publisher = new YouTubePublisher;

    expect(fn () => $publisher->publish($this->postPlatform))
        ->toThrow(\Exception::class, 'YouTube Shorts requires a video to publish.');
});

test('youtube publisher throws exception for non video media', function () {
    Media::factory()->create([
        'mediable_type' => 'postPlatform',
        'mediable_id' => $this->postPlatform->id,
        'mime_type' => 'image/jpeg',
    ]);

    $publisher = new YouTubePublisher;

    expect(fn () => $publisher->publish($this->postPlatform))
        ->toThrow(\Exception::class, 'YouTube Shorts only supports video content.');
});

test('youtube publisher throws exception when no refresh token for expired token', function () {
    $this->socialAccount->update([
        'token_expires_at' => now()->subHour(),
        'refresh_token' => null,
    ]);

    Media::factory()->create([
        'mediable_type' => 'postPlatform',
        'mediable_id' => $this->postPlatform->id,
        'mime_type' => 'video/mp4',
    ]);

    $publisher = new YouTubePublisher;

    expect(fn () => $publisher->publish($this->postPlatform))
        ->toThrow(TokenExpiredException::class, 'No refresh token available');
});

test('youtube publisher throws token expired exception on 401', function () {
    Media::factory()->create([
        'mediable_type' => 'postPlatform',
        'mediable_id' => $this->postPlatform->id,
        'mime_type' => 'video/mp4',
    ]);

    Http::fake([
        '*' => Http::response([
            'error' => 'invalid_token',
            'error_description' => 'Token has expired',
        ], 401),
    ]);

    $publisher = new YouTubePublisher;

    // The error happens before the API call because of file_get_contents
    // So we test the token expired scenario through refreshToken
    $this->socialAccount->update([
        'token_expires_at' => now()->subHour(),
        'refresh_token' => 'refresh-token',
    ]);

    expect(fn () => $publisher->publish($this->postPlatform))
        ->toThrow(TokenExpiredException::class);
});

test('youtube publisher throws token expired exception on invalid grant', function () {
    $this->socialAccount->update([
        'token_expires_at' => now()->subHour(),
        'refresh_token' => 'invalid-refresh-token',
    ]);

    Media::factory()->create([
        'mediable_type' => 'postPlatform',
        'mediable_id' => $this->postPlatform->id,
        'mime_type' => 'video/mp4',
    ]);

    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response([
            'error' => 'invalid_grant',
            'error_description' => 'Token has been revoked',
        ], 400),
    ]);

    $publisher = new YouTubePublisher;

    expect(fn () => $publisher->publish($this->postPlatform))
        ->toThrow(TokenExpiredException::class);
});

test('youtube publisher handles auth error reason', function () {
    $this->socialAccount->update([
        'token_expires_at' => now()->subHour(),
        'refresh_token' => 'refresh-token',
    ]);

    Media::factory()->create([
        'mediable_type' => 'postPlatform',
        'mediable_id' => $this->postPlatform->id,
        'mime_type' => 'video/mp4',
    ]);

    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response([
            'error' => [
                'code' => 401,
                'message' => 'Request had invalid authentication credentials',
                'errors' => [
                    ['reason' => 'authError'],
                ],
            ],
        ], 401),
    ]);

    $publisher = new YouTubePublisher;

    expect(fn () => $publisher->publish($this->postPlatform))
        ->toThrow(TokenExpiredException::class);
});
