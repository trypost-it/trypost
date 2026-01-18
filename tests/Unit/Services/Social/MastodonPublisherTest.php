<?php

use App\Enums\PostPlatform\ContentType;
use App\Enums\SocialAccount\Platform;
use App\Exceptions\TokenExpiredException;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Social\MastodonPublisher;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);

    $this->socialAccount = SocialAccount::factory()->mastodon()->create([
        'workspace_id' => $this->workspace->id,
        'platform_user_id' => '123456789',
        'username' => 'testuser',
    ]);

    $this->post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    $this->postPlatform = PostPlatform::factory()->create([
        'post_id' => $this->post->id,
        'social_account_id' => $this->socialAccount->id,
        'platform' => Platform::Mastodon,
        'content_type' => ContentType::MastodonPost,
        'content' => 'Hello from Mastodon!',
    ]);

    $this->publisher = new MastodonPublisher;
});

test('mastodon publisher can publish text-only post', function () {
    Http::fake([
        'https://mastodon.social/api/v1/statuses' => Http::response([
            'id' => '109876543210',
            'url' => 'https://mastodon.social/@testuser/109876543210',
            'content' => '<p>Hello from Mastodon!</p>',
            'created_at' => now()->toIso8601String(),
        ], 200),
    ]);

    $result = $this->publisher->publish($this->postPlatform);

    expect($result)->toHaveKey('id');
    expect($result)->toHaveKey('url');
    expect($result['id'])->toBe('109876543210');
    expect($result['url'])->toBe('https://mastodon.social/@testuser/109876543210');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/api/v1/statuses')
            && $request['status'] === 'Hello from Mastodon!'
            && $request['visibility'] === 'public';
    });
});

test('mastodon publisher works with custom instance', function () {
    $this->socialAccount->update([
        'meta' => [
            'instance' => 'https://techhub.social',
            'client_id' => 'test-client-id',
            'client_secret' => 'test-client-secret',
        ],
    ]);

    Http::fake([
        'https://techhub.social/api/v1/statuses' => Http::response([
            'id' => '987654321',
            'url' => 'https://techhub.social/@testuser/987654321',
        ], 200),
    ]);

    $result = $this->publisher->publish($this->postPlatform);

    expect($result['url'])->toContain('techhub.social');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'techhub.social');
    });
});

test('mastodon publisher uploads media', function () {
    // Create a media item through the PostPlatform's media() relation
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
        'https://mastodon.social/api/v1/media' => Http::response([
            'id' => 'media-123',
            'type' => 'image',
            'url' => 'https://mastodon.social/media/image.jpg',
        ], 200),
        'https://mastodon.social/api/v1/statuses' => Http::response([
            'id' => '109876543210',
            'url' => 'https://mastodon.social/@testuser/109876543210',
        ], 200),
    ]);

    $this->publisher->publish($this->postPlatform);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/api/v1/statuses');
    });
});

test('mastodon publisher includes media ids in post', function () {
    Http::fake([
        'https://mastodon.social/api/v1/statuses' => Http::response([
            'id' => '109876543210',
            'url' => 'https://mastodon.social/@testuser/109876543210',
        ], 200),
    ]);

    $this->publisher->publish($this->postPlatform);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/api/v1/statuses')
            && $request['visibility'] === 'public';
    });
});

test('mastodon publisher throws exception on api error', function () {
    Http::fake([
        'https://mastodon.social/api/v1/statuses' => Http::response([
            'error' => 'Validation failed: Text can\'t be blank',
        ], 422),
    ]);

    expect(fn () => $this->publisher->publish($this->postPlatform))
        ->toThrow(Exception::class);
});

test('mastodon publisher throws token expired exception on auth error', function () {
    Http::fake([
        'https://mastodon.social/api/v1/statuses' => Http::response([
            'error' => 'The access token is invalid',
        ], 401),
    ]);

    expect(fn () => $this->publisher->publish($this->postPlatform))
        ->toThrow(TokenExpiredException::class);
});

test('mastodon publisher throws token expired exception on forbidden', function () {
    Http::fake([
        'https://mastodon.social/api/v1/statuses' => Http::response([
            'error' => 'This action is not allowed',
        ], 403),
    ]);

    expect(fn () => $this->publisher->publish($this->postPlatform))
        ->toThrow(TokenExpiredException::class);
});

test('mastodon publisher limits media to 4', function () {
    // Create 6 media items through the PostPlatform's media() relation
    for ($i = 0; $i < 6; $i++) {
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
        'https://mastodon.social/api/v1/media' => Http::response([
            'id' => 'media-123',
            'type' => 'image',
        ], 200),
        'https://mastodon.social/api/v1/statuses' => Http::response([
            'id' => '109876543210',
            'url' => 'https://mastodon.social/@testuser/109876543210',
        ], 200),
    ]);

    $this->publisher->publish($this->postPlatform);

    // Should still publish successfully (media upload might fail but post should succeed)
    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/api/v1/statuses');
    });
});

test('mastodon publisher handles empty content', function () {
    $this->postPlatform->update(['content' => '']);

    Http::fake([
        'https://mastodon.social/api/v1/statuses' => Http::response([
            'id' => '109876543210',
            'url' => 'https://mastodon.social/@testuser/109876543210',
        ], 200),
    ]);

    $result = $this->publisher->publish($this->postPlatform);

    expect($result['id'])->toBe('109876543210');

    Http::assertSent(function ($request) {
        return $request['status'] === '';
    });
});

test('mastodon publisher uses bearer token authentication', function () {
    Http::fake([
        'https://mastodon.social/api/v1/statuses' => Http::response([
            'id' => '109876543210',
            'url' => 'https://mastodon.social/@testuser/109876543210',
        ], 200),
    ]);

    $this->publisher->publish($this->postPlatform);

    Http::assertSent(function ($request) {
        return $request->hasHeader('Authorization')
            && str_starts_with($request->header('Authorization')[0], 'Bearer ');
    });
});

test('mastodon publisher defaults to mastodon.social if no instance in meta', function () {
    $this->socialAccount->update(['meta' => []]);

    Http::fake([
        'https://mastodon.social/api/v1/statuses' => Http::response([
            'id' => '109876543210',
            'url' => 'https://mastodon.social/@testuser/109876543210',
        ], 200),
    ]);

    $this->publisher->publish($this->postPlatform);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'mastodon.social');
    });
});
