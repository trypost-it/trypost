<?php

declare(strict_types=1);

use App\Enums\PostPlatform\ContentType;
use App\Enums\SocialAccount\Platform;
use App\Exceptions\Social\TikTokPublishException;
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
        'content' => 'Check out this TikTok video!',
    ]);

    $this->postPlatform = PostPlatform::factory()->tiktok()->create([
        'post_id' => $this->post->id,
        'social_account_id' => $this->socialAccount->id,
        'platform' => Platform::TikTok,
        'content_type' => ContentType::TikTokVideo,
    ]);

    $this->publisher = new TikTokPublisher;
});

test('tiktok publisher throws exception when no media', function () {
    expect(fn () => $this->publisher->publish($this->postPlatform))
        ->toThrow(Exception::class, 'TikTok requires media (video or photos) to publish.');
});

test('tiktok publisher can publish video', function () {
    $this->post->update([
        'media' => [
            [
                'id' => 'test-media-video',
                'path' => 'media/2026-01/test-video.mp4',
                'url' => 'https://example.com/media/2026-01/test-video.mp4',
                'mime_type' => 'video/mp4',
                'original_filename' => 'test-video.mp4',
            ],
        ],
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
    $this->post->update([
        'media' => [
            [
                'id' => 'test-media-image',
                'path' => 'media/2026-01/image1.jpg',
                'url' => 'https://example.com/media/2026-01/image1.jpg',
                'mime_type' => 'image/jpeg',
                'original_filename' => 'image1.jpg',
            ],
        ],
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
        if (! str_contains($request->url(), '/post/publish/content/init/')) {
            return false;
        }
        $body = json_decode($request->body(), true);

        return data_get($body, 'post_info.description') === 'Check out this TikTok video!'
            && ! isset($body['post_info']['title']);
    });
});

test('tiktok publisher throws exception on api error', function () {
    $this->post->update([
        'media' => [
            [
                'id' => 'test-media-video',
                'path' => 'media/2026-01/test-video.mp4',
                'url' => 'https://example.com/media/2026-01/test-video.mp4',
                'mime_type' => 'video/mp4',
                'original_filename' => 'test-video.mp4',
            ],
        ],
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
    $this->post->update([
        'media' => [
            [
                'id' => 'test-media-video',
                'path' => 'media/2026-01/test-video.mp4',
                'url' => 'https://example.com/media/2026-01/test-video.mp4',
                'mime_type' => 'video/mp4',
                'original_filename' => 'test-video.mp4',
            ],
        ],
    ]);

    Http::fake([
        'https://open.tiktokapis.com/v2/post/publish/video/init/' => Http::response([
            'error' => [
                'code' => 'access_token_invalid',
                'message' => 'Access token is invalid',
            ],
        ], 401),
    ]);

    expect(fn () => $this->publisher->publish($this->postPlatform))
        ->toThrow(TokenExpiredException::class);
});

test('tiktok publisher refreshes token when expired', function () {
    $this->socialAccount->update(['token_expires_at' => now()->subHour()]);

    $this->post->update([
        'media' => [
            [
                'id' => 'test-media-video',
                'path' => 'media/2026-01/test-video.mp4',
                'url' => 'https://example.com/media/2026-01/test-video.mp4',
                'mime_type' => 'video/mp4',
                'original_filename' => 'test-video.mp4',
            ],
        ],
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

    $this->post->update([
        'media' => [
            [
                'id' => 'test-media-video',
                'path' => 'media/2026-01/test-video.mp4',
                'url' => 'https://example.com/media/2026-01/test-video.mp4',
                'mime_type' => 'video/mp4',
                'original_filename' => 'test-video.mp4',
            ],
        ],
    ]);

    expect(fn () => $this->publisher->publish($this->postPlatform))
        ->toThrow(TokenExpiredException::class, 'No refresh token available for TikTok account');
});

test('tiktok publisher throws exception for unsupported media type', function () {
    $this->post->update([
        'media' => [
            [
                'id' => 'test-media-doc',
                'path' => 'media/2026-01/doc.pdf',
                'url' => 'https://example.com/media/2026-01/doc.pdf',
                'mime_type' => 'application/pdf',
                'original_filename' => 'doc.pdf',
            ],
        ],
    ]);

    expect(fn () => $this->publisher->publish($this->postPlatform))
        ->toThrow(Exception::class, 'TikTok only supports video or image content.');
});

test('tiktok publisher builds correct profile url when username present', function () {
    $this->post->update([
        'media' => [
            [
                'id' => 'test-media-video',
                'path' => 'media/2026-01/test-video.mp4',
                'url' => 'https://example.com/media/2026-01/test-video.mp4',
                'mime_type' => 'video/mp4',
                'original_filename' => 'test-video.mp4',
            ],
        ],
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

    $this->post->update([
        'media' => [
            [
                'id' => 'test-media-video',
                'path' => 'media/2026-01/test-video.mp4',
                'url' => 'https://example.com/media/2026-01/test-video.mp4',
                'mime_type' => 'video/mp4',
                'original_filename' => 'test-video.mp4',
            ],
        ],
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

test('tiktok publisher falls back to self only when creator info fails', function () {
    $this->post->update([
        'media' => [
            [
                'id' => 'test-media-video',
                'path' => 'media/2026-01/test-video.mp4',
                'url' => 'https://example.com/media/2026-01/test-video.mp4',
                'mime_type' => 'video/mp4',
                'original_filename' => 'test-video.mp4',
            ],
        ],
    ]);

    Http::fake([
        // creator_info/query returns 500 — publisher should fall back to SELF_ONLY
        'https://open.tiktokapis.com/v2/post/publish/creator_info/query/' => Http::response([
            'error' => ['code' => 'internal_error', 'message' => 'Internal server error'],
        ], 500),
        'https://open.tiktokapis.com/v2/post/publish/video/init/' => Http::response([
            'data' => ['publish_id' => 'pub_fallback_123'],
        ], 200),
        'https://open.tiktokapis.com/v2/post/publish/status/fetch/' => Http::response([
            'data' => [
                'status' => 'PUBLISH_COMPLETE',
                'publish_id' => 'pub_fallback_123',
            ],
        ], 200),
    ]);

    $result = $this->publisher->publish($this->postPlatform);

    expect($result)->toHaveKey('id');
    expect($result['id'])->toBe('pub_fallback_123');

    // Assert SELF_ONLY was used in the video init payload
    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/post/publish/video/init/')) {
            return false;
        }
        $body = json_decode($request->body(), true);

        return data_get($body, 'post_info.privacy_level') === 'SELF_ONLY';
    });
});

test('tiktok publisher throws exception when publish fails', function () {
    $this->post->update([
        'media' => [
            [
                'id' => 'test-media-video',
                'path' => 'media/2026-01/test-video.mp4',
                'url' => 'https://example.com/media/2026-01/test-video.mp4',
                'mime_type' => 'video/mp4',
                'original_filename' => 'test-video.mp4',
            ],
        ],
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
        ->toThrow(TikTokPublishException::class);
});

test('tiktok publisher sends meta settings in video publish request', function () {
    $this->postPlatform->update([
        'meta' => [
            'privacy_level' => 'PUBLIC_TO_EVERYONE',
            'allow_comments' => true,
            'allow_duet' => false,
            'allow_stitch' => true,
            'is_aigc' => true,
            'brand_content_toggle' => true,
            'brand_organic_toggle' => false,
        ],
    ]);

    $this->post->update([
        'media' => [
            [
                'id' => 'test-media-video',
                'path' => 'media/2026-01/test-video.mp4',
                'url' => 'https://example.com/media/2026-01/test-video.mp4',
                'mime_type' => 'video/mp4',
                'original_filename' => 'test-video.mp4',
            ],
        ],
    ]);

    Http::fake([
        'https://open.tiktokapis.com/v2/post/publish/creator_info/query/' => Http::response([
            'data' => [
                'privacy_level_options' => ['PUBLIC_TO_EVERYONE', 'SELF_ONLY'],
            ],
        ], 200),
        'https://open.tiktokapis.com/v2/post/publish/video/init/' => Http::response([
            'data' => ['publish_id' => 'pub_meta_123'],
        ], 200),
        'https://open.tiktokapis.com/v2/post/publish/status/fetch/' => Http::response([
            'data' => ['status' => 'PUBLISH_COMPLETE'],
        ], 200),
    ]);

    $this->publisher->publish($this->postPlatform);

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/post/publish/video/init/')) {
            return false;
        }
        $body = json_decode($request->body(), true);
        $postInfo = data_get($body, 'post_info');

        return $postInfo['privacy_level'] === 'PUBLIC_TO_EVERYONE'
            && $postInfo['disable_comment'] === false
            && $postInfo['disable_duet'] === true
            && $postInfo['disable_stitch'] === false
            && $postInfo['is_aigc'] === true
            && $postInfo['brand_content_toggle'] === true
            && ! isset($postInfo['brand_organic_toggle']);
    });
});

test('tiktok publisher sends auto_add_music for photo posts', function () {
    $this->postPlatform->update([
        'meta' => [
            'privacy_level' => 'SELF_ONLY',
            'allow_comments' => true,
            'auto_add_music' => true,
        ],
    ]);

    $this->post->update([
        'media' => [
            [
                'id' => 'test-media-photo',
                'path' => 'media/2026-01/photo.jpg',
                'url' => 'https://example.com/media/2026-01/photo.jpg',
                'mime_type' => 'image/jpeg',
                'original_filename' => 'photo.jpg',
            ],
        ],
    ]);

    Http::fake([
        'https://open.tiktokapis.com/v2/post/publish/creator_info/query/' => Http::response([
            'data' => ['privacy_level_options' => ['SELF_ONLY']],
        ], 200),
        'https://open.tiktokapis.com/v2/post/publish/content/init/' => Http::response([
            'data' => ['publish_id' => 'pub_music_123'],
        ], 200),
        'https://open.tiktokapis.com/v2/post/publish/status/fetch/' => Http::response([
            'data' => ['status' => 'PUBLISH_COMPLETE'],
        ], 200),
    ]);

    $this->publisher->publish($this->postPlatform);

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/post/publish/content/init/')) {
            return false;
        }
        $body = json_decode($request->body(), true);
        $postInfo = data_get($body, 'post_info');

        return $postInfo['auto_add_music'] === true
            && ! isset($postInfo['disable_duet'])
            && ! isset($postInfo['disable_stitch'])
            && ! isset($postInfo['is_aigc'])
            && ! isset($postInfo['title'])
            && isset($postInfo['description']);
    });
});

test('tiktok publisher does not send auto_add_music for video posts', function () {
    $this->postPlatform->update([
        'meta' => [
            'privacy_level' => 'SELF_ONLY',
            'auto_add_music' => true,
        ],
    ]);

    $this->post->update([
        'media' => [
            [
                'id' => 'test-media-video',
                'path' => 'media/2026-01/video.mp4',
                'url' => 'https://example.com/media/2026-01/video.mp4',
                'mime_type' => 'video/mp4',
                'original_filename' => 'video.mp4',
            ],
        ],
    ]);

    Http::fake([
        'https://open.tiktokapis.com/v2/post/publish/creator_info/query/' => Http::response([
            'data' => ['privacy_level_options' => ['SELF_ONLY']],
        ], 200),
        'https://open.tiktokapis.com/v2/post/publish/video/init/' => Http::response([
            'data' => ['publish_id' => 'pub_vid_123'],
        ], 200),
        'https://open.tiktokapis.com/v2/post/publish/status/fetch/' => Http::response([
            'data' => ['status' => 'PUBLISH_COMPLETE'],
        ], 200),
    ]);

    $this->publisher->publish($this->postPlatform);

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/post/publish/video/init/')) {
            return false;
        }
        $body = json_decode($request->body(), true);
        $postInfo = data_get($body, 'post_info');

        return ! isset($postInfo['auto_add_music']);
    });
});

test('tiktok publisher uses default settings when meta is empty', function () {
    $this->postPlatform->update(['meta' => null]);

    $this->post->update([
        'media' => [
            [
                'id' => 'test-media-video',
                'path' => 'media/2026-01/test-video.mp4',
                'url' => 'https://example.com/media/2026-01/test-video.mp4',
                'mime_type' => 'video/mp4',
                'original_filename' => 'test-video.mp4',
            ],
        ],
    ]);

    Http::fake([
        'https://open.tiktokapis.com/v2/post/publish/creator_info/query/' => Http::response([
            'data' => [
                'privacy_level_options' => ['PUBLIC_TO_EVERYONE', 'FOLLOWER_OF_CREATOR', 'SELF_ONLY'],
            ],
        ], 200),
        'https://open.tiktokapis.com/v2/post/publish/video/init/' => Http::response([
            'data' => ['publish_id' => 'pub_default_123'],
        ], 200),
        'https://open.tiktokapis.com/v2/post/publish/status/fetch/' => Http::response([
            'data' => ['status' => 'PUBLISH_COMPLETE'],
        ], 200),
    ]);

    $this->publisher->publish($this->postPlatform);

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/post/publish/video/init/')) {
            return false;
        }
        $body = json_decode($request->body(), true);
        $postInfo = data_get($body, 'post_info');

        // When meta is empty, uses creator_info privacy and defaults
        return $postInfo['privacy_level'] === 'PUBLIC_TO_EVERYONE'
            && $postInfo['disable_comment'] === false
            && $postInfo['disable_duet'] === true
            && $postInfo['disable_stitch'] === true
            && ! isset($postInfo['is_aigc'])
            && ! isset($postInfo['brand_content_toggle']);
    });
});

test('tiktok publisher sends video caption in title field, never description', function () {
    $this->post->update([
        'media' => [
            [
                'id' => 'test-media-video',
                'path' => 'media/2026-01/test-video.mp4',
                'url' => 'https://example.com/media/2026-01/test-video.mp4',
                'mime_type' => 'video/mp4',
                'original_filename' => 'test-video.mp4',
            ],
        ],
        'content' => 'My video caption',
    ]);
    $this->postPlatform->update(['meta' => ['privacy_level' => 'SELF_ONLY']]);

    Http::fake([
        'https://open.tiktokapis.com/v2/post/publish/creator_info/query/' => Http::response([
            'data' => [
                'privacy_level_options' => ['SELF_ONLY'],
            ],
        ], 200),
        'https://open.tiktokapis.com/v2/post/publish/video/init/' => Http::response([
            'data' => ['publish_id' => 'pub_video_123'],
        ], 200),
        'https://open.tiktokapis.com/v2/post/publish/status/fetch/' => Http::response([
            'data' => ['status' => 'PUBLISH_COMPLETE', 'publish_id' => 'pub_video_123'],
        ], 200),
    ]);

    $this->publisher->publish($this->postPlatform);

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/post/publish/video/init/')) {
            return false;
        }
        $body = json_decode($request->body(), true);

        return data_get($body, 'post_info.title') === 'My video caption'
            && ! isset($body['post_info']['description']);
    });
});
