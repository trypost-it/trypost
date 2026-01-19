<?php

use App\Enums\PostPlatform\ContentType;
use App\Enums\SocialAccount\Platform;
use App\Exceptions\TokenExpiredException;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Social\InstagramPublisher;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);

    $this->socialAccount = SocialAccount::factory()->instagram()->create([
        'workspace_id' => $this->workspace->id,
        'platform_user_id' => 'ig_123456789',
        'username' => 'testuser',
        'token_expires_at' => now()->addDays(60),
    ]);

    $this->post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    $this->postPlatform = PostPlatform::factory()->create([
        'post_id' => $this->post->id,
        'social_account_id' => $this->socialAccount->id,
        'platform' => Platform::Instagram,
        'content_type' => ContentType::InstagramFeed,
        'content' => 'Hello from Instagram!',
    ]);

    $this->publisher = new InstagramPublisher;
});

test('instagram publisher throws exception without media', function () {
    expect(fn () => $this->publisher->publish($this->postPlatform))
        ->toThrow(\Exception::class, 'Instagram requires at least one image or video');
});

test('instagram publisher can publish single image', function () {
    $this->postPlatform->media()->create([
        'collection' => 'default',
        'type' => 'image',
        'path' => 'media/2026-01/test-image.jpg',
        'original_filename' => 'test.jpg',
        'mime_type' => 'image/jpeg',
        'size' => 12345,
        'order' => 0,
        'meta' => ['width' => 1920, 'height' => 1080],
    ]);

    Http::fake([
        'https://graph.instagram.com/v24.0/ig_123456789/media' => Http::response([
            'id' => 'container-123',
        ], 200),
        'https://graph.instagram.com/v24.0/container-123*' => Http::response([
            'status_code' => 'FINISHED',
        ], 200),
        'https://graph.instagram.com/v24.0/ig_123456789/media_publish' => Http::response([
            'id' => 'media-123456789',
        ], 200),
        'https://graph.instagram.com/v24.0/media-123456789*' => Http::response([
            'permalink' => 'https://www.instagram.com/p/ABC123/',
        ], 200),
    ]);

    $result = $this->publisher->publish($this->postPlatform);

    expect($result)->toHaveKey('id');
    expect($result)->toHaveKey('url');
    expect($result['id'])->toBe('media-123456789');
    expect($result['url'])->toBe('https://www.instagram.com/p/ABC123/');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/ig_123456789/media');
    });
});

test('instagram publisher can publish reel', function () {
    $this->postPlatform->update(['content_type' => ContentType::InstagramReel]);

    $this->postPlatform->media()->create([
        'collection' => 'default',
        'type' => 'video',
        'path' => 'media/2026-01/test-video.mp4',
        'original_filename' => 'test.mp4',
        'mime_type' => 'video/mp4',
        'size' => 1234567,
        'order' => 0,
        'meta' => ['width' => 1080, 'height' => 1920, 'duration' => 30],
    ]);

    Http::fake([
        'https://graph.instagram.com/v24.0/ig_123456789/media' => Http::response([
            'id' => 'container-123',
        ], 200),
        'https://graph.instagram.com/v24.0/container-123*' => Http::response([
            'status_code' => 'FINISHED',
        ], 200),
        'https://graph.instagram.com/v24.0/ig_123456789/media_publish' => Http::response([
            'id' => 'reel-123456789',
        ], 200),
        'https://graph.instagram.com/v24.0/reel-123456789*' => Http::response([
            'permalink' => 'https://www.instagram.com/reel/ABC123/',
        ], 200),
    ]);

    $result = $this->publisher->publish($this->postPlatform);

    expect($result['id'])->toBe('reel-123456789');
    expect($result['url'])->toBe('https://www.instagram.com/reel/ABC123/');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/ig_123456789/media')
            && (str_contains($request->body(), 'REELS') || str_contains($request->url(), 'media_publish'));
    });
});

test('instagram publisher can publish image story', function () {
    $this->postPlatform->update(['content_type' => ContentType::InstagramStory]);

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
        'https://graph.instagram.com/v24.0/ig_123456789/media' => Http::response([
            'id' => 'story-container-123',
        ], 200),
        'https://graph.instagram.com/v24.0/story-container-123*' => Http::response([
            'status_code' => 'FINISHED',
        ], 200),
        'https://graph.instagram.com/v24.0/ig_123456789/media_publish' => Http::response([
            'id' => 'story-123456789',
        ], 200),
        'https://graph.instagram.com/v24.0/story-123456789*' => Http::response([
            'permalink' => 'https://www.instagram.com/stories/testuser/123/',
        ], 200),
    ]);

    $result = $this->publisher->publish($this->postPlatform);

    expect($result['id'])->toBe('story-123456789');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/ig_123456789/media');
    });
});

test('instagram publisher can publish video story', function () {
    $this->postPlatform->update(['content_type' => ContentType::InstagramStory]);

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
        'https://graph.instagram.com/v24.0/ig_123456789/media' => Http::response([
            'id' => 'story-container-123',
        ], 200),
        'https://graph.instagram.com/v24.0/story-container-123*' => Http::response([
            'status_code' => 'FINISHED',
        ], 200),
        'https://graph.instagram.com/v24.0/ig_123456789/media_publish' => Http::response([
            'id' => 'story-video-123456789',
        ], 200),
        'https://graph.instagram.com/v24.0/story-video-123456789*' => Http::response([
            'permalink' => 'https://www.instagram.com/stories/testuser/456/',
        ], 200),
    ]);

    $result = $this->publisher->publish($this->postPlatform);

    expect($result['id'])->toBe('story-video-123456789');
});

test('instagram publisher can publish carousel', function () {
    // Create multiple media items
    for ($i = 0; $i < 3; $i++) {
        $this->postPlatform->media()->create([
            'collection' => 'default',
            'type' => 'image',
            'path' => "media/2026-01/test-image-{$i}.jpg",
            'original_filename' => "test-{$i}.jpg",
            'mime_type' => 'image/jpeg',
            'size' => 12345,
            'order' => $i,
            'meta' => ['width' => 1920, 'height' => 1080],
        ]);
    }

    Http::fake([
        'https://graph.instagram.com/v24.0/ig_123456789/media' => Http::sequence()
            ->push(['id' => 'child-1'], 200)
            ->push(['id' => 'child-2'], 200)
            ->push(['id' => 'child-3'], 200)
            ->push(['id' => 'carousel-container-123'], 200),
        'https://graph.instagram.com/v24.0/carousel-container-123*' => Http::response([
            'status_code' => 'FINISHED',
        ], 200),
        'https://graph.instagram.com/v24.0/ig_123456789/media_publish' => Http::response([
            'id' => 'carousel-123456789',
        ], 200),
        'https://graph.instagram.com/v24.0/carousel-123456789*' => Http::response([
            'permalink' => 'https://www.instagram.com/p/CAROUSEL123/',
        ], 200),
    ]);

    $result = $this->publisher->publish($this->postPlatform);

    expect($result['id'])->toBe('carousel-123456789');
    expect($result['url'])->toBe('https://www.instagram.com/p/CAROUSEL123/');
});

test('instagram publisher can publish carousel with videos', function () {
    // Create image and video mix
    $this->postPlatform->media()->create([
        'collection' => 'default',
        'type' => 'image',
        'path' => 'media/2026-01/test-image.jpg',
        'original_filename' => 'test.jpg',
        'mime_type' => 'image/jpeg',
        'size' => 12345,
        'order' => 0,
    ]);
    $this->postPlatform->media()->create([
        'collection' => 'default',
        'type' => 'video',
        'path' => 'media/2026-01/test-video.mp4',
        'original_filename' => 'test.mp4',
        'mime_type' => 'video/mp4',
        'size' => 1234567,
        'order' => 1,
    ]);

    Http::fake([
        'https://graph.instagram.com/v24.0/ig_123456789/media' => Http::sequence()
            ->push(['id' => 'child-1'], 200)
            ->push(['id' => 'child-2'], 200)
            ->push(['id' => 'carousel-container-123'], 200),
        'https://graph.instagram.com/v24.0/child-2*' => Http::response([
            'status_code' => 'FINISHED',
        ], 200),
        'https://graph.instagram.com/v24.0/carousel-container-123*' => Http::response([
            'status_code' => 'FINISHED',
        ], 200),
        'https://graph.instagram.com/v24.0/ig_123456789/media_publish' => Http::response([
            'id' => 'carousel-mix-123456789',
        ], 200),
        'https://graph.instagram.com/v24.0/carousel-mix-123456789*' => Http::response([
            'permalink' => 'https://www.instagram.com/p/CAROUSELMIX/',
        ], 200),
    ]);

    $result = $this->publisher->publish($this->postPlatform);

    expect($result['id'])->toBe('carousel-mix-123456789');
});

test('instagram publisher throws exception on api error', function () {
    $this->postPlatform->media()->create([
        'collection' => 'default',
        'type' => 'image',
        'path' => 'media/2026-01/test-image.jpg',
        'original_filename' => 'test.jpg',
        'mime_type' => 'image/jpeg',
        'size' => 12345,
        'order' => 0,
    ]);

    Http::fake([
        'https://graph.instagram.com/v24.0/ig_123456789/media' => Http::response([
            'error' => [
                'message' => 'Invalid parameter',
                'type' => 'GraphMethodException',
                'code' => 100,
            ],
        ], 400),
    ]);

    expect(fn () => $this->publisher->publish($this->postPlatform))
        ->toThrow(Exception::class);
});

test('instagram publisher throws token expired exception on oauth error', function () {
    $this->postPlatform->media()->create([
        'collection' => 'default',
        'type' => 'image',
        'path' => 'media/2026-01/test-image.jpg',
        'original_filename' => 'test.jpg',
        'mime_type' => 'image/jpeg',
        'size' => 12345,
        'order' => 0,
    ]);

    Http::fake([
        'https://graph.instagram.com/v24.0/ig_123456789/media' => Http::response([
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

test('instagram publisher throws token expired exception on session expired subcode', function () {
    $this->postPlatform->media()->create([
        'collection' => 'default',
        'type' => 'image',
        'path' => 'media/2026-01/test-image.jpg',
        'original_filename' => 'test.jpg',
        'mime_type' => 'image/jpeg',
        'size' => 12345,
        'order' => 0,
    ]);

    Http::fake([
        'https://graph.instagram.com/v24.0/ig_123456789/media' => Http::response([
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

test('instagram publisher throws exception for unsupported content type', function () {
    $this->postPlatform->update(['content_type' => ContentType::LinkedInPost]);

    $this->postPlatform->media()->create([
        'collection' => 'default',
        'type' => 'image',
        'path' => 'media/2026-01/test-image.jpg',
        'original_filename' => 'test.jpg',
        'mime_type' => 'image/jpeg',
        'size' => 12345,
        'order' => 0,
    ]);

    expect(fn () => $this->publisher->publish($this->postPlatform))
        ->toThrow(Exception::class, 'Unsupported Instagram content type');
});

test('instagram publisher throws exception when no container id returned', function () {
    $this->postPlatform->media()->create([
        'collection' => 'default',
        'type' => 'image',
        'path' => 'media/2026-01/test-image.jpg',
        'original_filename' => 'test.jpg',
        'mime_type' => 'image/jpeg',
        'size' => 12345,
        'order' => 0,
    ]);

    Http::fake([
        'https://graph.instagram.com/v24.0/ig_123456789/media' => Http::response([
            'success' => true,
            // No id returned
        ], 200),
    ]);

    expect(fn () => $this->publisher->publish($this->postPlatform))
        ->toThrow(Exception::class, 'No container ID returned');
});

test('instagram publisher handles media processing error', function () {
    $this->postPlatform->media()->create([
        'collection' => 'default',
        'type' => 'image',
        'path' => 'media/2026-01/test-image.jpg',
        'original_filename' => 'test.jpg',
        'mime_type' => 'image/jpeg',
        'size' => 12345,
        'order' => 0,
    ]);

    Http::fake([
        'https://graph.instagram.com/v24.0/ig_123456789/media' => Http::response([
            'id' => 'container-123',
        ], 200),
        'https://graph.instagram.com/v24.0/container-123*' => Http::response([
            'status_code' => 'ERROR',
        ], 200),
    ]);

    expect(fn () => $this->publisher->publish($this->postPlatform))
        ->toThrow(Exception::class, 'Instagram media processing failed');
});

test('instagram publisher waits for media processing', function () {
    $this->postPlatform->media()->create([
        'collection' => 'default',
        'type' => 'image',
        'path' => 'media/2026-01/test-image.jpg',
        'original_filename' => 'test.jpg',
        'mime_type' => 'image/jpeg',
        'size' => 12345,
        'order' => 0,
    ]);

    Http::fake([
        'https://graph.instagram.com/v24.0/ig_123456789/media' => Http::response([
            'id' => 'container-123',
        ], 200),
        'https://graph.instagram.com/v24.0/container-123*' => Http::sequence()
            ->push(['status_code' => 'IN_PROGRESS'], 200)
            ->push(['status_code' => 'IN_PROGRESS'], 200)
            ->push(['status_code' => 'FINISHED'], 200),
        'https://graph.instagram.com/v24.0/ig_123456789/media_publish' => Http::response([
            'id' => 'media-123456789',
        ], 200),
        'https://graph.instagram.com/v24.0/media-123456789*' => Http::response([
            'permalink' => 'https://www.instagram.com/p/ABC123/',
        ], 200),
    ]);

    $result = $this->publisher->publish($this->postPlatform);

    expect($result['id'])->toBe('media-123456789');
});

test('instagram publisher throws exception when all carousel items fail', function () {
    // Create multiple media items
    for ($i = 0; $i < 3; $i++) {
        $this->postPlatform->media()->create([
            'collection' => 'default',
            'type' => 'image',
            'path' => "media/2026-01/test-image-{$i}.jpg",
            'original_filename' => "test-{$i}.jpg",
            'mime_type' => 'image/jpeg',
            'size' => 12345,
            'order' => $i,
        ]);
    }

    Http::fake([
        'https://graph.instagram.com/v24.0/ig_123456789/media' => Http::response([
            'error' => ['message' => 'Upload failed'],
        ], 400),
    ]);

    expect(fn () => $this->publisher->publish($this->postPlatform))
        ->toThrow(Exception::class, 'Failed to create any carousel items');
});

test('instagram publisher handles publish failure', function () {
    $this->postPlatform->media()->create([
        'collection' => 'default',
        'type' => 'image',
        'path' => 'media/2026-01/test-image.jpg',
        'original_filename' => 'test.jpg',
        'mime_type' => 'image/jpeg',
        'size' => 12345,
        'order' => 0,
    ]);

    Http::fake([
        'https://graph.instagram.com/v24.0/ig_123456789/media' => Http::response([
            'id' => 'container-123',
        ], 200),
        'https://graph.instagram.com/v24.0/container-123*' => Http::response([
            'status_code' => 'FINISHED',
        ], 200),
        'https://graph.instagram.com/v24.0/ig_123456789/media_publish' => Http::response([
            'error' => [
                'message' => 'Publish failed',
                'type' => 'GraphMethodException',
                'code' => 100,
            ],
        ], 400),
    ]);

    expect(fn () => $this->publisher->publish($this->postPlatform))
        ->toThrow(Exception::class);
});
