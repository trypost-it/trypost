<?php

declare(strict_types=1);

use App\Enums\PostPlatform\ContentType;
use App\Enums\SocialAccount\Platform;
use App\Exceptions\TokenExpiredException;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Media\MediaOptimizer;
use App\Services\Social\PinterestPublisher;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);

    $this->socialAccount = SocialAccount::factory()->pinterest()->create([
        'workspace_id' => $this->workspace->id,
        'platform_user_id' => 'pinterest_user_123',
        'username' => 'pinner',
        'token_expires_at' => now()->addDays(30),
        'meta' => [
            'default_board_id' => 'board_123',
        ],
    ]);

    $this->post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'content' => 'Check out this pin!',
    ]);

    $this->postPlatform = PostPlatform::factory()->pinterest()->create([
        'post_id' => $this->post->id,
        'social_account_id' => $this->socialAccount->id,
        'platform' => Platform::Pinterest,
        'content_type' => ContentType::PinterestPin,
        'meta' => ['board_id' => 'board_123'],
    ]);

    $this->publisher = new PinterestPublisher;

    // Mock MediaOptimizer to return the same temp file path
    $mockOptimizer = Mockery::mock(MediaOptimizer::class);
    $mockOptimizer->shouldReceive('optimizeImage')->andReturnUsing(function (string $tempFile) {
        // Copy to a new temp file to simulate optimization
        $optimized = tempnam(sys_get_temp_dir(), 'pin_opt_');
        copy($tempFile, $optimized);

        return $optimized;
    });
    app()->instance(MediaOptimizer::class, $mockOptimizer);
});

test('pinterest publisher can publish image pin', function () {
    $this->post->update([
        'media' => [
            [
                'id' => 'test-media-id',
                'path' => 'media/2026-01/image.jpg',
                'url' => 'https://example.com/media/2026-01/image.jpg',
                'mime_type' => 'image/jpeg',
                'original_filename' => 'image.jpg',
            ],
        ],
    ]);

    Http::fake([
        '*/v5/pins' => Http::response([
            'id' => 'pin_123456',
        ], 200),
        '*' => Http::response('fake-image-content', 200),
    ]);

    $result = $this->publisher->publish($this->postPlatform);

    expect($result)->toHaveKey('id');
    expect($result)->toHaveKey('url');
    expect($result['id'])->toBe('pin_123456');
    expect($result['url'])->toBe('https://pinterest.com/pin/pin_123456');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/v5/pins');
    });
});

test('pinterest publisher throws exception when no media for pin', function () {
    expect(fn () => $this->publisher->publish($this->postPlatform))
        ->toThrow(Exception::class, 'Pinterest requires at least one image');
});

test('pinterest publisher throws exception when no board id', function () {
    $this->postPlatform->update(['meta' => []]);
    $this->socialAccount->update(['meta' => []]);

    $this->post->update([
        'media' => [
            [
                'id' => 'test-media-id',
                'path' => 'media/2026-01/image.jpg',
                'url' => 'https://example.com/media/2026-01/image.jpg',
                'mime_type' => 'image/jpeg',
                'original_filename' => 'image.jpg',
            ],
        ],
    ]);

    expect(fn () => $this->publisher->publish($this->postPlatform))
        ->toThrow(Exception::class, 'Pinterest board_id is required');
});

test('pinterest publisher uses default board id from account', function () {
    $this->postPlatform->update(['meta' => []]); // No board_id in post meta

    $this->post->update([
        'media' => [
            [
                'id' => 'test-media-id',
                'path' => 'media/2026-01/image.jpg',
                'url' => 'https://example.com/media/2026-01/image.jpg',
                'mime_type' => 'image/jpeg',
                'original_filename' => 'image.jpg',
            ],
        ],
    ]);

    Http::fake([
        '*/v5/pins' => Http::response([
            'id' => 'pin_123456',
        ], 200),
        '*' => Http::response('fake-image-content', 200),
    ]);

    $this->publisher->publish($this->postPlatform);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/v5/pins')
            && $request['board_id'] === 'board_123'; // from account meta
    });
});

test('pinterest publisher can publish carousel', function () {
    $mediaItems = [];
    for ($i = 1; $i <= 3; $i++) {
        $mediaItems[] = [
            'id' => "test-media-{$i}",
            'path' => "media/2026-01/image{$i}.jpg",
            'url' => "https://example.com/media/2026-01/image{$i}.jpg",
            'mime_type' => 'image/jpeg',
            'original_filename' => "image{$i}.jpg",
        ];
    }
    $this->postPlatform->update(['content_type' => ContentType::PinterestCarousel]);

    $this->post->update([

        'media' => $mediaItems,

    ]);

    Http::fake([
        '*/v5/pins' => Http::response([
            'id' => 'carousel_pin_123',
        ], 200),
    ]);

    $result = $this->publisher->publish($this->postPlatform);

    expect($result['id'])->toBe('carousel_pin_123');

    Http::assertSent(function ($request) {
        return $request['media_source']['source_type'] === 'multiple_image_urls'
            && count($request['media_source']['items']) === 3;
    });
});

test('pinterest publisher throws exception for carousel with less than 2 images', function () {
    $this->postPlatform->update(['content_type' => ContentType::PinterestCarousel]);

    $this->post->update([
        'media' => [
            [
                'id' => 'test-media-id',
                'path' => 'media/2026-01/image.jpg',
                'url' => 'https://example.com/media/2026-01/image.jpg',
                'mime_type' => 'image/jpeg',
                'original_filename' => 'image.jpg',
            ],
        ],
    ]);

    expect(fn () => $this->publisher->publish($this->postPlatform))
        ->toThrow(Exception::class, 'Pinterest carousel requires 2-5 images');
});

test('pinterest publisher throws exception for carousel with more than 5 images', function () {
    $mediaItems = [];
    for ($i = 1; $i <= 6; $i++) {
        $mediaItems[] = [
            'id' => "test-media-{$i}",
            'path' => "media/2026-01/image{$i}.jpg",
            'url' => "https://example.com/media/2026-01/image{$i}.jpg",
            'mime_type' => 'image/jpeg',
            'original_filename' => "image{$i}.jpg",
        ];
    }
    $this->postPlatform->update(['content_type' => ContentType::PinterestCarousel]);

    $this->post->update([

        'media' => $mediaItems,

    ]);

    expect(fn () => $this->publisher->publish($this->postPlatform))
        ->toThrow(Exception::class, 'Pinterest carousel requires 2-5 images');
});

test('pinterest publisher throws exception on api error', function () {
    $this->post->update([
        'media' => [
            [
                'id' => 'test-media-id',
                'path' => 'media/2026-01/image.jpg',
                'url' => 'https://example.com/media/2026-01/image.jpg',
                'mime_type' => 'image/jpeg',
                'original_filename' => 'image.jpg',
            ],
        ],
    ]);

    Http::fake([
        '*/v5/pins' => Http::response([
            'code' => 400,
            'message' => 'Invalid request',
        ], 400),
        '*' => Http::response('fake-image-content', 200),
    ]);

    expect(fn () => $this->publisher->publish($this->postPlatform))
        ->toThrow(Exception::class);
});

test('pinterest publisher throws token expired exception on auth error', function () {
    $this->post->update([
        'media' => [
            [
                'id' => 'test-media-id',
                'path' => 'media/2026-01/image.jpg',
                'url' => 'https://example.com/media/2026-01/image.jpg',
                'mime_type' => 'image/jpeg',
                'original_filename' => 'image.jpg',
            ],
        ],
    ]);

    Http::fake([
        '*/v5/pins' => Http::response([
            'code' => 1,
            'message' => 'Invalid access token',
        ], 401),
        '*' => Http::response('fake-image-content', 200),
    ]);

    expect(fn () => $this->publisher->publish($this->postPlatform))
        ->toThrow(TokenExpiredException::class);
});

test('pinterest publisher refreshes token when expired', function () {
    $this->socialAccount->update(['token_expires_at' => now()->subHour()]);

    $this->post->update([
        'media' => [
            [
                'id' => 'test-media-id',
                'path' => 'media/2026-01/image.jpg',
                'url' => 'https://example.com/media/2026-01/image.jpg',
                'mime_type' => 'image/jpeg',
                'original_filename' => 'image.jpg',
            ],
        ],
    ]);

    Http::fake([
        '*/v5/oauth/token' => Http::response([
            'access_token' => 'new-access-token',
            'refresh_token' => 'new-refresh-token',
            'expires_in' => 2592000,
        ], 200),
        '*/v5/pins' => Http::response([
            'id' => 'pin_123456',
        ], 200),
        '*' => Http::response('fake-image-content', 200),
    ]);

    $this->publisher->publish($this->postPlatform);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'oauth/token');
    });

    $this->socialAccount->refresh();
    expect($this->socialAccount->access_token)->toBe('new-access-token');
});

test('pinterest publisher includes title and link when provided', function () {
    $this->postPlatform->update([
        'meta' => [
            'board_id' => 'board_123',
            'title' => 'My Pin Title',
            'link' => 'https://example.com/my-page',
        ],
    ]);

    $this->post->update([
        'media' => [
            [
                'id' => 'test-media-id',
                'path' => 'media/2026-01/image.jpg',
                'url' => 'https://example.com/media/2026-01/image.jpg',
                'mime_type' => 'image/jpeg',
                'original_filename' => 'image.jpg',
            ],
        ],
    ]);

    Http::fake([
        '*/v5/pins' => Http::response([
            'id' => 'pin_123456',
        ], 200),
        '*' => Http::response('fake-image-content', 200),
    ]);

    $this->publisher->publish($this->postPlatform);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/v5/pins')
            && $request['title'] === 'My Pin Title'
            && $request['link'] === 'https://example.com/my-page';
    });
});

test('pinterest publisher can get boards', function () {
    Http::fake([
        '*/v5/boards*' => Http::response([
            'items' => [
                ['id' => 'board_1', 'name' => 'Board 1'],
                ['id' => 'board_2', 'name' => 'Board 2'],
            ],
        ], 200),
    ]);

    $boards = $this->publisher->getBoards($this->socialAccount);

    expect($boards)->toHaveCount(2);
    expect($boards[0]['id'])->toBe('board_1');
});

test('pinterest publisher can publish video pin', function () {
    $this->postPlatform->update(['content_type' => ContentType::PinterestVideoPin]);

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

    $s3UploadUrl = 'https://pinterest-media-upload.s3.amazonaws.com/upload';

    Http::fake(function ($request) use ($s3UploadUrl) {
        $url = $request->url();

        // Step 1: Register media
        if (str_contains($url, '/v5/media') && $request->method() === 'POST') {
            return Http::response([
                'media_id' => 'media_video_789',
                'upload_url' => $s3UploadUrl,
                'upload_parameters' => [
                    'key' => 'uploads/video.mp4',
                    'AWSAccessKeyId' => 'FAKE_KEY',
                ],
            ], 201);
        }

        // Step 2: S3 upload
        if ($url === $s3UploadUrl) {
            return Http::response('', 204);
        }

        // Step 3: Media status check
        if (str_contains($url, '/v5/media/media_video_789')) {
            return Http::response(['status' => 'succeeded'], 200);
        }

        // Step 4: Create pin
        if (str_contains($url, '/v5/pins')) {
            return Http::response(['id' => 'video_pin_999'], 200);
        }

        // Video download
        return Http::response('fake-video-content', 200);
    });

    $result = $this->publisher->publish($this->postPlatform);

    expect($result)->toHaveKey('id');
    expect($result)->toHaveKey('url');
    expect($result['id'])->toBe('video_pin_999');
    expect($result['url'])->toBe('https://pinterest.com/pin/video_pin_999');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/v5/pins')
            && data_get($request->data(), 'media_source.source_type') === 'video_id';
    });
});

test('pinterest publisher throws exception for unsupported content type', function () {
    $this->postPlatform->update(['content_type' => ContentType::InstagramFeed]);

    $this->post->update([
        'media' => [
            [
                'id' => 'test-media-id',
                'path' => 'media/2026-01/image.jpg',
                'url' => 'https://example.com/media/2026-01/image.jpg',
                'mime_type' => 'image/jpeg',
                'original_filename' => 'image.jpg',
            ],
        ],
    ]);

    expect(fn () => $this->publisher->publish($this->postPlatform))
        ->toThrow(Exception::class, 'Unsupported content type');
});
