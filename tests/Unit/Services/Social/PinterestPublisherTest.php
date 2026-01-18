<?php

use App\Enums\PostPlatform\ContentType;
use App\Enums\SocialAccount\Platform;
use App\Exceptions\TokenExpiredException;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
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
    ]);

    $this->postPlatform = PostPlatform::factory()->pinterest()->create([
        'post_id' => $this->post->id,
        'social_account_id' => $this->socialAccount->id,
        'platform' => Platform::Pinterest,
        'content_type' => ContentType::PinterestPin,
        'content' => 'Check out this pin!',
        'meta' => ['board_id' => 'board_123'],
    ]);

    $this->publisher = new PinterestPublisher;
});

test('pinterest publisher can publish image pin', function () {
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
        '*/v5/pins' => Http::response([
            'id' => 'pin_123456',
        ], 200),
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
        ->toThrow(Exception::class, 'Pinterest board_id is required');
});

test('pinterest publisher uses default board id from account', function () {
    $this->postPlatform->update(['meta' => []]); // No board_id in post meta

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
        '*/v5/pins' => Http::response([
            'id' => 'pin_123456',
        ], 200),
    ]);

    $this->publisher->publish($this->postPlatform);

    Http::assertSent(function ($request) {
        return $request['board_id'] === 'board_123'; // from account meta
    });
});

test('pinterest publisher can publish carousel', function () {
    $this->postPlatform->update(['content_type' => ContentType::PinterestCarousel]);

    // Create 3 images for carousel
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
        ->toThrow(Exception::class, 'Pinterest carousel requires 2-5 images');
});

test('pinterest publisher throws exception for carousel with more than 5 images', function () {
    $this->postPlatform->update(['content_type' => ContentType::PinterestCarousel]);

    // Create 6 images
    for ($i = 1; $i <= 6; $i++) {
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

    expect(fn () => $this->publisher->publish($this->postPlatform))
        ->toThrow(Exception::class, 'Pinterest carousel requires 2-5 images');
});

test('pinterest publisher throws exception on api error', function () {
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
        '*/v5/pins' => Http::response([
            'code' => 400,
            'message' => 'Invalid request',
        ], 400),
    ]);

    expect(fn () => $this->publisher->publish($this->postPlatform))
        ->toThrow(Exception::class);
});

test('pinterest publisher throws token expired exception on auth error', function () {
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
        '*/v5/pins' => Http::response([
            'code' => 1,
            'message' => 'Invalid access token',
        ], 401),
    ]);

    expect(fn () => $this->publisher->publish($this->postPlatform))
        ->toThrow(TokenExpiredException::class);
});

test('pinterest publisher refreshes token when expired', function () {
    $this->socialAccount->update(['token_expires_at' => now()->subHour()]);

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
        '*/v5/oauth/token' => Http::response([
            'access_token' => 'new-access-token',
            'refresh_token' => 'new-refresh-token',
            'expires_in' => 2592000,
        ], 200),
        '*/v5/pins' => Http::response([
            'id' => 'pin_123456',
        ], 200),
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
        '*/v5/pins' => Http::response([
            'id' => 'pin_123456',
        ], 200),
    ]);

    $this->publisher->publish($this->postPlatform);

    Http::assertSent(function ($request) {
        return $request['title'] === 'My Pin Title'
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

test('pinterest publisher throws exception for unsupported content type', function () {
    $this->postPlatform->update(['content_type' => ContentType::InstagramFeed]);

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
        ->toThrow(Exception::class, 'Unsupported content type');
});
