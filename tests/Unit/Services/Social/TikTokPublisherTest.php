<?php

use App\Enums\PostPlatform\ContentType;
use App\Enums\SocialAccount\Platform;
use App\Exceptions\TokenExpiredException;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Social\TikTokPublisher;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);

    $this->socialAccount = SocialAccount::factory()->tiktok()->create([
        'workspace_id' => $this->workspace->id,
        'platform_user_id' => 'tiktok123',
        'username' => 'tiktoker',
        'token_expires_at' => now()->addDays(1),
    ]);

    $this->post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    $this->postPlatform = PostPlatform::factory()->tiktok()->create([
        'post_id' => $this->post->id,
        'social_account_id' => $this->socialAccount->id,
        'platform' => Platform::TikTok,
        'content_type' => ContentType::TikTokVideo,
        'content' => 'Check out this TikTok video!',
    ]);

    $this->publisher = new TikTokPublisher;
});

test('tiktok publisher throws exception when no media', function () {
    expect(fn () => $this->publisher->publish($this->postPlatform))
        ->toThrow(Exception::class, 'TikTok requires media (video or photos) to publish.');
});

test('tiktok publisher can publish video', function () {
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
        'https://open.tiktokapis.com/v2/post/publish/video/init/' => Http::response([
            'data' => ['publish_id' => 'pub_123'],
        ], 200),
        'https://open.tiktokapis.com/v2/post/publish/status/fetch/' => Http::response([
            'data' => [
                'status' => 'PUBLISH_COMPLETE',
                'publish_id' => 'pub_123',
            ],
        ], 200),
    ]);

    $result = $this->publisher->publish($this->postPlatform);

    expect($result)->toHaveKey('id');
    expect($result)->toHaveKey('url');
    expect($result['id'])->toBe('pub_123');
    expect($result['url'])->toContain('tiktok.com/@tiktoker');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/post/publish/video/init/');
    });
});

test('tiktok publisher can publish photos', function () {
    $this->postPlatform->media()->create([
        'collection' => 'default',
        'type' => 'image',
        'path' => 'media/2026-01/image1.jpg',
        'original_filename' => 'image1.jpg',
        'mime_type' => 'image/jpeg',
        'size' => 512000,
        'order' => 0,
    ]);

    Http::fake([
        'https://open.tiktokapis.com/v2/post/publish/content/init/' => Http::response([
            'data' => ['publish_id' => 'pub_photo_123'],
        ], 200),
        'https://open.tiktokapis.com/v2/post/publish/status/fetch/' => Http::response([
            'data' => [
                'status' => 'PUBLISH_COMPLETE',
                'publish_id' => 'pub_photo_123',
            ],
        ], 200),
    ]);

    $result = $this->publisher->publish($this->postPlatform);

    expect($result)->toHaveKey('id');
    expect($result['id'])->toBe('pub_photo_123');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/post/publish/content/init/');
    });
});

test('tiktok publisher throws exception on api error', function () {
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
        'https://open.tiktokapis.com/v2/post/publish/video/init/' => Http::response([
            'error' => [
                'code' => 'invalid_request',
                'message' => 'Invalid request',
            ],
        ], 400),
    ]);

    expect(fn () => $this->publisher->publish($this->postPlatform))
        ->toThrow(Exception::class);
});

test('tiktok publisher throws token expired exception on auth error', function () {
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
        'https://open.tiktokapis.com/v2/post/publish/video/init/' => Http::response([
            'error' => [
                'code' => 'access_token_expired',
                'message' => 'Access token has expired',
            ],
        ], 401),
    ]);

    expect(fn () => $this->publisher->publish($this->postPlatform))
        ->toThrow(TokenExpiredException::class);
});

test('tiktok publisher refreshes token when expired', function () {
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
        'https://open.tiktokapis.com/v2/oauth/token/' => Http::response([
            'access_token' => 'new-access-token',
            'refresh_token' => 'new-refresh-token',
            'expires_in' => 86400,
        ], 200),
        'https://open.tiktokapis.com/v2/post/publish/video/init/' => Http::response([
            'data' => ['publish_id' => 'pub_123'],
        ], 200),
        'https://open.tiktokapis.com/v2/post/publish/status/fetch/' => Http::response([
            'data' => ['status' => 'PUBLISH_COMPLETE'],
        ], 200),
    ]);

    $this->publisher->publish($this->postPlatform);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'oauth/token');
    });

    $this->socialAccount->refresh();
    expect($this->socialAccount->access_token)->toBe('new-access-token');
});

test('tiktok publisher throws exception when no refresh token available', function () {
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
        ->toThrow(TokenExpiredException::class, 'No refresh token available for TikTok account');
});

test('tiktok publisher throws exception for unsupported media type', function () {
    $this->postPlatform->media()->create([
        'collection' => 'default',
        'type' => 'document',
        'path' => 'media/2026-01/doc.pdf',
        'original_filename' => 'doc.pdf',
        'mime_type' => 'application/pdf',
        'size' => 512000,
        'order' => 0,
    ]);

    expect(fn () => $this->publisher->publish($this->postPlatform))
        ->toThrow(Exception::class, 'TikTok only supports video or image content.');
});

test('tiktok publisher builds correct profile url when username present', function () {
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
        'https://open.tiktokapis.com/v2/post/publish/video/init/' => Http::response([
            'data' => ['publish_id' => 'pub_123'],
        ], 200),
        'https://open.tiktokapis.com/v2/post/publish/status/fetch/' => Http::response([
            'data' => ['status' => 'PUBLISH_COMPLETE'],
        ], 200),
    ]);

    $result = $this->publisher->publish($this->postPlatform);

    expect($result['url'])->toBe('https://www.tiktok.com/@tiktoker');
});

test('tiktok publisher returns null url when username missing', function () {
    $this->socialAccount->update(['username' => null]);

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
        'https://open.tiktokapis.com/v2/post/publish/video/init/' => Http::response([
            'data' => ['publish_id' => 'pub_123'],
        ], 200),
        'https://open.tiktokapis.com/v2/post/publish/status/fetch/' => Http::response([
            'data' => ['status' => 'PUBLISH_COMPLETE'],
        ], 200),
    ]);

    $result = $this->publisher->publish($this->postPlatform);

    expect($result['url'])->toBeNull();
});

test('tiktok publisher throws exception when publish fails', function () {
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
        'https://open.tiktokapis.com/v2/post/publish/video/init/' => Http::response([
            'data' => ['publish_id' => 'pub_123'],
        ], 200),
        'https://open.tiktokapis.com/v2/post/publish/status/fetch/' => Http::response([
            'data' => [
                'status' => 'FAILED',
                'fail_reason' => 'video_rejected',
            ],
        ], 200),
    ]);

    expect(fn () => $this->publisher->publish($this->postPlatform))
        ->toThrow(Exception::class, 'TikTok publish failed: video_rejected');
});
