<?php

use App\Enums\PostPlatform\ContentType;
use App\Enums\SocialAccount\Platform;
use App\Exceptions\TokenExpiredException;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Social\FacebookPublisher;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);

    $this->socialAccount = SocialAccount::factory()->facebook()->create([
        'workspace_id' => $this->workspace->id,
        'platform_user_id' => 'page_123',
        'username' => 'myfbpage',
        'token_expires_at' => null, // Facebook page tokens don't expire
        'meta' => [
            'page_id' => 'page_123',
            'user_id' => 'fb_user_123',
        ],
    ]);

    $this->post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    $this->postPlatform = PostPlatform::factory()->facebook()->create([
        'post_id' => $this->post->id,
        'social_account_id' => $this->socialAccount->id,
        'platform' => Platform::Facebook,
        'content_type' => ContentType::FacebookPost,
        'content' => 'Check out this Facebook post!',
    ]);

    $this->publisher = new FacebookPublisher;
});

test('facebook publisher can publish text only post', function () {
    Http::fake([
        '*/page_123/feed' => Http::response([
            'id' => 'page_123_post_456',
        ], 200),
    ]);

    $result = $this->publisher->publish($this->postPlatform);

    expect($result)->toHaveKey('id');
    expect($result)->toHaveKey('url');
    expect($result['id'])->toBe('page_123_post_456');
    expect($result['url'])->toBe('https://www.facebook.com/page_123_post_456');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/page_123/feed')
            && $request['message'] === 'Check out this Facebook post!';
    });
});

test('facebook publisher can publish single image post', function () {
    $this->postPlatform->media()->create([
        'collection' => 'default',
        'type' => 'image',
        'path' => 'media/2026-01/image.jpg',
        'original_filename' => 'image.jpg',
        'mime_type' => 'image/jpeg',
        'size' => 512000,
        'order' => 0,
    ]);

    Http::fake([
        '*/page_123/photos' => Http::response([
            'id' => 'photo_123',
            'post_id' => 'page_123_photo_post_456',
        ], 200),
    ]);

    $result = $this->publisher->publish($this->postPlatform);

    expect($result)->toHaveKey('id');
    expect($result['id'])->toBe('page_123_photo_post_456');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/page_123/photos')
            && $request['message'] === 'Check out this Facebook post!';
    });
});

test('facebook publisher can publish multi image post', function () {
    // Create 3 images
    for ($i = 1; $i <= 3; $i++) {
        $this->postPlatform->media()->create([
            'collection' => 'default',
            'type' => 'image',
            'path' => "media/2026-01/image{$i}.jpg",
            'original_filename' => "image{$i}.jpg",
            'mime_type' => 'image/jpeg',
            'size' => 512000,
            'order' => $i - 1,
        ]);
    }

    Http::fake([
        '*/page_123/photos' => Http::sequence()
            ->push(['id' => 'photo_1'], 200)
            ->push(['id' => 'photo_2'], 200)
            ->push(['id' => 'photo_3'], 200),
        '*/page_123/feed' => Http::response([
            'id' => 'page_123_multi_post_789',
        ], 200),
    ]);

    $result = $this->publisher->publish($this->postPlatform);

    expect($result)->toHaveKey('id');
    expect($result['id'])->toBe('page_123_multi_post_789');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/page_123/feed');
    });
});

test('facebook publisher can publish video post', function () {
    $this->postPlatform->media()->create([
        'collection' => 'default',
        'type' => 'video',
        'path' => 'media/2026-01/video.mp4',
        'original_filename' => 'video.mp4',
        'mime_type' => 'video/mp4',
        'size' => 10240000,
        'order' => 0,
    ]);

    Http::fake([
        '*/page_123/videos' => Http::response([
            'id' => 'video_123',
        ], 200),
    ]);

    $result = $this->publisher->publish($this->postPlatform);

    expect($result)->toHaveKey('id');
    expect($result['id'])->toBe('video_123');
    expect($result['url'])->toBe('https://www.facebook.com/page_123/videos/video_123');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/page_123/videos')
            && $request['description'] === 'Check out this Facebook post!';
    });
});

test('facebook publisher can publish reel', function () {
    $this->postPlatform->update(['content_type' => ContentType::FacebookReel]);

    $this->postPlatform->media()->create([
        'collection' => 'default',
        'type' => 'video',
        'path' => 'media/2026-01/reel.mp4',
        'original_filename' => 'reel.mp4',
        'mime_type' => 'video/mp4',
        'size' => 5120000,
        'order' => 0,
    ]);

    Http::fake([
        '*/page_123/video_reels' => Http::sequence()
            ->push(['video_id' => 'reel_video_123'], 200)
            ->push(['id' => 'reel_123', 'success' => true], 200),
        '*/reel_video_123' => Http::response(['success' => true], 200),
    ]);

    $result = $this->publisher->publish($this->postPlatform);

    expect($result)->toHaveKey('id');
    expect($result['id'])->toBe('reel_123');
    expect($result['url'])->toBe('https://www.facebook.com/reel/reel_123');
});

test('facebook publisher can publish image story', function () {
    $this->postPlatform->update(['content_type' => ContentType::FacebookStory]);

    $this->postPlatform->media()->create([
        'collection' => 'default',
        'type' => 'image',
        'path' => 'media/2026-01/story.jpg',
        'original_filename' => 'story.jpg',
        'mime_type' => 'image/jpeg',
        'size' => 512000,
        'order' => 0,
    ]);

    Http::fake([
        '*/page_123/photos' => Http::response([
            'id' => 'photo_story_123',
        ], 200),
        '*/page_123/photo_stories' => Http::response([
            'post_id' => 'story_post_123',
        ], 200),
    ]);

    $result = $this->publisher->publish($this->postPlatform);

    expect($result)->toHaveKey('id');
    expect($result['id'])->toBe('story_post_123');
    expect($result['url'])->toContain('/stories/page_123/');
});

test('facebook publisher can publish video story', function () {
    $this->postPlatform->update(['content_type' => ContentType::FacebookStory]);

    $this->postPlatform->media()->create([
        'collection' => 'default',
        'type' => 'video',
        'path' => 'media/2026-01/story.mp4',
        'original_filename' => 'story.mp4',
        'mime_type' => 'video/mp4',
        'size' => 5120000,
        'order' => 0,
    ]);

    Http::fake([
        '*/page_123/video_stories' => Http::sequence()
            ->push(['video_id' => 'story_video_123'], 200)
            ->push(['post_id' => 'video_story_post_123'], 200),
        '*/story_video_123' => Http::response(['success' => true], 200),
    ]);

    $result = $this->publisher->publish($this->postPlatform);

    expect($result)->toHaveKey('id');
    expect($result['id'])->toBe('video_story_post_123');
});

test('facebook publisher throws exception on api error', function () {
    Http::fake([
        '*/page_123/feed' => Http::response([
            'error' => [
                'message' => 'Invalid request',
                'type' => 'GraphMethodException',
                'code' => 100,
            ],
        ], 400),
    ]);

    expect(fn () => $this->publisher->publish($this->postPlatform))
        ->toThrow(Exception::class);
});

test('facebook publisher throws token expired exception on oauth error', function () {
    Http::fake([
        '*/page_123/feed' => Http::response([
            'error' => [
                'message' => 'Error validating access token',
                'type' => 'OAuthException',
                'code' => 190,
            ],
        ], 400),
    ]);

    expect(fn () => $this->publisher->publish($this->postPlatform))
        ->toThrow(TokenExpiredException::class);
});

test('facebook publisher throws token expired exception on session expired subcode', function () {
    Http::fake([
        '*/page_123/feed' => Http::response([
            'error' => [
                'message' => 'Session has expired',
                'type' => 'OAuthException',
                'code' => 190,
                'error_subcode' => 463,
            ],
        ], 400),
    ]);

    expect(fn () => $this->publisher->publish($this->postPlatform))
        ->toThrow(TokenExpiredException::class);
});

test('facebook publisher throws exception for unsupported content type', function () {
    $this->postPlatform->update(['content_type' => ContentType::InstagramFeed]);

    expect(fn () => $this->publisher->publish($this->postPlatform))
        ->toThrow(Exception::class, 'Unsupported Facebook content type');
});

test('facebook publisher throws exception when multi image upload fails', function () {
    // Create 3 images
    for ($i = 1; $i <= 3; $i++) {
        $this->postPlatform->media()->create([
            'collection' => 'default',
            'type' => 'image',
            'path' => "media/2026-01/image{$i}.jpg",
            'original_filename' => "image{$i}.jpg",
            'mime_type' => 'image/jpeg',
            'size' => 512000,
            'order' => $i - 1,
        ]);
    }

    Http::fake([
        '*/page_123/photos' => Http::response([
            'error' => ['message' => 'Upload failed'],
        ], 400),
    ]);

    expect(fn () => $this->publisher->publish($this->postPlatform))
        ->toThrow(Exception::class, 'Failed to upload any images to Facebook');
});

test('facebook publisher throws exception for unsupported media type', function () {
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
        ->toThrow(Exception::class, 'Unsupported media type for Facebook');
});
