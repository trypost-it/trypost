<?php

use App\Enums\PostPlatform\ContentType;
use App\Enums\SocialAccount\Platform;
use App\Exceptions\TokenExpiredException;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Social\ThreadsPublisher;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);

    $this->socialAccount = SocialAccount::factory()->threads()->create([
        'workspace_id' => $this->workspace->id,
        'platform_user_id' => '123456789',
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
        'platform' => Platform::Threads,
        'content_type' => ContentType::ThreadsPost,
        'content' => 'Hello from Threads!',
    ]);

    $this->publisher = new ThreadsPublisher;
});

test('threads publisher can publish text-only post', function () {
    Http::fake([
        'https://graph.threads.net/v1.0/123456789/threads' => Http::response([
            'id' => 'container-123',
        ], 200),
        'https://graph.threads.net/v1.0/123456789/threads_publish' => Http::response([
            'id' => 'post-123456789',
        ], 200),
        'https://graph.threads.net/v1.0/post-123456789*' => Http::response([
            'permalink' => 'https://www.threads.net/@testuser/post/ABC123',
        ], 200),
    ]);

    $result = $this->publisher->publish($this->postPlatform);

    expect($result)->toHaveKey('id');
    expect($result)->toHaveKey('url');
    expect($result['id'])->toBe('post-123456789');
    expect($result['url'])->toBe('https://www.threads.net/@testuser/post/ABC123');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/threads')
            && str_contains($request->url(), '123456789');
    });
});

test('threads publisher can publish image post', function () {
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
        'https://graph.threads.net/v1.0/123456789/threads' => Http::response([
            'id' => 'container-123',
        ], 200),
        'https://graph.threads.net/v1.0/container-123*' => Http::response([
            'status' => 'FINISHED',
        ], 200),
        'https://graph.threads.net/v1.0/123456789/threads_publish' => Http::response([
            'id' => 'post-123456789',
        ], 200),
        'https://graph.threads.net/v1.0/post-123456789*' => Http::response([
            'permalink' => 'https://www.threads.net/@testuser/post/ABC123',
        ], 200),
    ]);

    $result = $this->publisher->publish($this->postPlatform);

    expect($result['id'])->toBe('post-123456789');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/threads');
    });
});

test('threads publisher can publish video post', function () {
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
        'https://graph.threads.net/v1.0/123456789/threads' => Http::response([
            'id' => 'container-123',
        ], 200),
        'https://graph.threads.net/v1.0/container-123*' => Http::response([
            'status' => 'FINISHED',
        ], 200),
        'https://graph.threads.net/v1.0/123456789/threads_publish' => Http::response([
            'id' => 'post-123456789',
        ], 200),
        'https://graph.threads.net/v1.0/post-123456789*' => Http::response([
            'permalink' => 'https://www.threads.net/@testuser/post/ABC123',
        ], 200),
    ]);

    $result = $this->publisher->publish($this->postPlatform);

    expect($result['id'])->toBe('post-123456789');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/threads');
    });
});

test('threads publisher can publish carousel', function () {
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
        'https://graph.threads.net/v1.0/123456789/threads' => Http::response([
            'id' => 'container-123',
        ], 200),
        'https://graph.threads.net/v1.0/container-123*' => Http::response([
            'status' => 'FINISHED',
        ], 200),
        'https://graph.threads.net/v1.0/123456789/threads_publish' => Http::response([
            'id' => 'post-123456789',
        ], 200),
        'https://graph.threads.net/v1.0/post-123456789*' => Http::response([
            'permalink' => 'https://www.threads.net/@testuser/post/ABC123',
        ], 200),
    ]);

    $result = $this->publisher->publish($this->postPlatform);

    expect($result['id'])->toBe('post-123456789');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/threads');
    });
});

test('threads publisher throws exception on api error', function () {
    Http::fake([
        'https://graph.threads.net/v1.0/123456789/threads' => Http::response([
            'error' => [
                'message' => 'Invalid parameter',
                'type' => 'OAuthException',
                'code' => 100,
            ],
        ], 400),
    ]);

    expect(fn () => $this->publisher->publish($this->postPlatform))
        ->toThrow(Exception::class);
});

test('threads publisher throws token expired exception on auth error', function () {
    Http::fake([
        'https://graph.threads.net/v1.0/123456789/threads' => Http::response([
            'error' => [
                'message' => 'Error validating access token',
                'type' => 'OAuthException',
                'code' => 190,
            ],
        ], 401),
    ]);

    expect(fn () => $this->publisher->publish($this->postPlatform))
        ->toThrow(TokenExpiredException::class);
});

test('threads publisher refreshes token when expired', function () {
    $this->socialAccount->update(['token_expires_at' => now()->subHour()]);

    Http::fake([
        'https://graph.threads.net/refresh_access_token*' => Http::response([
            'access_token' => 'new-long-lived-token',
            'expires_in' => 5184000,
        ], 200),
        'https://graph.threads.net/v1.0/123456789/threads' => Http::response([
            'id' => 'container-123',
        ], 200),
        'https://graph.threads.net/v1.0/123456789/threads_publish' => Http::response([
            'id' => 'post-123456789',
        ], 200),
        'https://graph.threads.net/v1.0/post-123456789*' => Http::response([
            'permalink' => 'https://www.threads.net/@testuser/post/ABC123',
        ], 200),
    ]);

    $this->publisher->publish($this->postPlatform);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'refresh_access_token');
    });

    $this->socialAccount->refresh();
    expect($this->socialAccount->access_token)->toBe('new-long-lived-token');
});

test('threads publisher waits for media processing', function () {
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
        'https://graph.threads.net/v1.0/123456789/threads' => Http::response([
            'id' => 'container-123',
        ], 200),
        'https://graph.threads.net/v1.0/container-123*' => Http::sequence()
            ->push(['status' => 'IN_PROGRESS'], 200)
            ->push(['status' => 'IN_PROGRESS'], 200)
            ->push(['status' => 'FINISHED'], 200),
        'https://graph.threads.net/v1.0/123456789/threads_publish' => Http::response([
            'id' => 'post-123456789',
        ], 200),
        'https://graph.threads.net/v1.0/post-123456789*' => Http::response([
            'permalink' => 'https://www.threads.net/@testuser/post/ABC123',
        ], 200),
    ]);

    $result = $this->publisher->publish($this->postPlatform);

    expect($result['id'])->toBe('post-123456789');
});

test('threads publisher handles media processing error', function () {
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
        'https://graph.threads.net/v1.0/123456789/threads' => Http::response([
            'id' => 'container-123',
        ], 200),
        'https://graph.threads.net/v1.0/container-123*' => Http::response([
            'status' => 'ERROR',
            'error_message' => 'Media upload failed',
        ], 200),
    ]);

    expect(fn () => $this->publisher->publish($this->postPlatform))
        ->toThrow(Exception::class, 'Threads media processing failed');
});
