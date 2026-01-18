<?php

use App\Enums\PostPlatform\ContentType;
use App\Enums\SocialAccount\Platform;
use App\Exceptions\TokenExpiredException;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Social\YouTubePublisher;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);

    $this->socialAccount = SocialAccount::factory()->youtube()->create([
        'workspace_id' => $this->workspace->id,
        'platform_user_id' => 'UC_channel_123',
        'username' => 'mychannel',
        'token_expires_at' => now()->addDays(7),
        'meta' => [
            'channel_id' => 'UC_channel_123',
            'google_user_id' => 'google_user_123',
        ],
    ]);

    $this->post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    $this->postPlatform = PostPlatform::factory()->youtube()->create([
        'post_id' => $this->post->id,
        'social_account_id' => $this->socialAccount->id,
        'platform' => Platform::YouTube,
        'content_type' => ContentType::YouTubeShort,
        'content' => 'Check out this YouTube Short!',
    ]);

    $this->publisher = new YouTubePublisher;
});

test('youtube publisher throws exception when no media', function () {
    expect(fn () => $this->publisher->publish($this->postPlatform))
        ->toThrow(Exception::class, 'YouTube Shorts requires a video to publish.');
});

test('youtube publisher throws exception for non-video content', function () {
    $this->postPlatform->media()->create([
        'collection' => 'default',
        'type' => 'image',
        'path' => 'media/2026-01/image.jpg',
        'original_filename' => 'image.jpg',
        'mime_type' => 'image/jpeg',
        'size' => 512000,
        'order' => 0,
    ]);

    expect(fn () => $this->publisher->publish($this->postPlatform))
        ->toThrow(Exception::class, 'YouTube Shorts only supports video content.');
});

test('youtube publisher refreshes token when expired', function () {
    $this->socialAccount->update(['token_expires_at' => now()->subHour()]);

    $this->postPlatform->media()->create([
        'collection' => 'default',
        'type' => 'video',
        'path' => 'media/2026-01/test-video.mp4',
        'original_filename' => 'test-video.mp4',
        'mime_type' => 'video/mp4',
        'size' => 1024000,
        'order' => 0,
    ]);

    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response([
            'access_token' => 'new-access-token',
            'refresh_token' => 'new-refresh-token',
            'expires_in' => 3600,
        ], 200),
        '*' => Http::response(['error' => ['message' => 'Test']], 400),
    ]);

    try {
        $this->publisher->publish($this->postPlatform);
    } catch (\Exception $e) {
        // Expected to fail on upload, but token should be refreshed
    }

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'oauth2.googleapis.com/token');
    });

    $this->socialAccount->refresh();
    expect($this->socialAccount->access_token)->toBe('new-access-token');
});

test('youtube publisher throws exception when no refresh token available', function () {
    $this->socialAccount->update([
        'token_expires_at' => now()->subHour(),
        'refresh_token' => null,
    ]);

    $this->postPlatform->media()->create([
        'collection' => 'default',
        'type' => 'video',
        'path' => 'media/2026-01/test-video.mp4',
        'original_filename' => 'test-video.mp4',
        'mime_type' => 'video/mp4',
        'size' => 1024000,
        'order' => 0,
    ]);

    expect(fn () => $this->publisher->publish($this->postPlatform))
        ->toThrow(TokenExpiredException::class, 'No refresh token available for YouTube account');
});

test('youtube publisher throws exception on api init error', function () {
    $this->postPlatform->media()->create([
        'collection' => 'default',
        'type' => 'video',
        'path' => 'media/2026-01/test-video.mp4',
        'original_filename' => 'test-video.mp4',
        'mime_type' => 'video/mp4',
        'size' => 1024000,
        'order' => 0,
    ]);

    Http::fake([
        'https://www.googleapis.com/upload/youtube/v3/videos*' => Http::response([
            'error' => [
                'message' => 'Invalid request',
            ],
        ], 400),
    ]);

    expect(fn () => $this->publisher->publish($this->postPlatform))
        ->toThrow(Exception::class);
});

// Note: Testing token expiration on auth error would require mocking file_get_contents
// which is used to fetch video content. The token refresh test above covers the token
// expiration handling. Full integration tests should cover the 401 error scenario.
