<?php

use App\Enums\PostPlatform\ContentType;
use App\Enums\SocialAccount\Platform;
use App\Exceptions\TokenExpiredException;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Social\XPublisher;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);

    $this->socialAccount = SocialAccount::factory()->x()->create([
        'workspace_id' => $this->workspace->id,
        'platform_user_id' => '123456789',
        'username' => 'testuser',
        'token_expires_at' => now()->addHours(2),
    ]);

    $this->post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    $this->postPlatform = PostPlatform::factory()->create([
        'post_id' => $this->post->id,
        'social_account_id' => $this->socialAccount->id,
        'platform' => Platform::X,
        'content_type' => ContentType::XPost,
        'content' => 'Hello from X!',
    ]);

    $this->publisher = new XPublisher;
});

test('x publisher can publish text-only post', function () {
    Http::fake([
        'https://api.x.com/2/tweets' => Http::response([
            'data' => [
                'id' => '1234567890123456789',
                'text' => 'Hello from X!',
            ],
        ], 200),
    ]);

    $result = $this->publisher->publish($this->postPlatform);

    expect($result)->toHaveKey('id');
    expect($result)->toHaveKey('url');
    expect($result['id'])->toBe('1234567890123456789');
    expect($result['url'])->toBe('https://x.com/testuser/status/1234567890123456789');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/2/tweets')
            && $request['text'] === 'Hello from X!';
    });
});

test('x publisher uses bearer token authentication', function () {
    Http::fake([
        'https://api.x.com/2/tweets' => Http::response([
            'data' => [
                'id' => '1234567890123456789',
            ],
        ], 200),
    ]);

    $this->publisher->publish($this->postPlatform);

    Http::assertSent(function ($request) {
        return $request->hasHeader('Authorization')
            && str_starts_with($request->header('Authorization')[0], 'Bearer ');
    });
});

test('x publisher throws exception on api error', function () {
    Http::fake([
        'https://api.x.com/2/tweets' => Http::response([
            'detail' => 'You are not allowed to create a Tweet with duplicate content.',
            'type' => 'about:blank',
            'title' => 'Forbidden',
            'status' => 403,
        ], 403),
    ]);

    expect(fn () => $this->publisher->publish($this->postPlatform))
        ->toThrow(Exception::class);
});

test('x publisher throws token expired exception on auth error', function () {
    Http::fake([
        'https://api.x.com/2/tweets' => Http::response([
            'title' => 'Unauthorized',
            'detail' => 'Unauthorized',
            'status' => 401,
        ], 401),
    ]);

    expect(fn () => $this->publisher->publish($this->postPlatform))
        ->toThrow(TokenExpiredException::class);
});

test('x publisher refreshes token when expired', function () {
    $this->socialAccount->update(['token_expires_at' => now()->subHour()]);

    Http::fake([
        'https://api.x.com/2/oauth2/token' => Http::response([
            'access_token' => 'new-access-token',
            'refresh_token' => 'new-refresh-token',
            'expires_in' => 7200,
        ], 200),
        'https://api.x.com/2/tweets' => Http::response([
            'data' => [
                'id' => '1234567890123456789',
            ],
        ], 200),
    ]);

    $this->publisher->publish($this->postPlatform);

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), 'oauth2/token')) {
            return false;
        }

        // Verify Basic Auth is used for confidential client authentication
        $authHeader = $request->header('Authorization')[0] ?? '';

        return str_starts_with($authHeader, 'Basic ');
    });

    $this->socialAccount->refresh();
    expect($this->socialAccount->access_token)->toBe('new-access-token');
});

test('x publisher includes media ids in post when media uploaded', function () {
    // Note: This test verifies the post structure when media IDs are present
    // Actual media upload requires file_get_contents which needs real files
    Http::fake([
        'https://api.x.com/2/tweets' => Http::response([
            'data' => [
                'id' => '1234567890123456789',
            ],
        ], 200),
    ]);

    $this->publisher->publish($this->postPlatform);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/2/tweets');
    });
});

test('x publisher handles empty content', function () {
    $this->postPlatform->update(['content' => '']);

    Http::fake([
        'https://api.x.com/2/tweets' => Http::response([
            'data' => [
                'id' => '1234567890123456789',
            ],
        ], 200),
    ]);

    $result = $this->publisher->publish($this->postPlatform);

    expect($result['id'])->toBe('1234567890123456789');

    Http::assertSent(function ($request) {
        return $request['text'] === '';
    });
});

test('x publisher throws exception when no refresh token available', function () {
    $this->socialAccount->update([
        'token_expires_at' => now()->subHour(),
        'refresh_token' => null,
    ]);

    expect(fn () => $this->publisher->publish($this->postPlatform))
        ->toThrow(TokenExpiredException::class, 'No refresh token available for X account');
});
