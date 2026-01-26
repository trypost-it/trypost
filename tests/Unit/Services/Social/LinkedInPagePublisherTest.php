<?php

use App\Enums\PostPlatform\ContentType;
use App\Enums\SocialAccount\Platform;
use App\Exceptions\TokenExpiredException;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Social\LinkedInPagePublisher;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);

    $this->socialAccount = SocialAccount::factory()->linkedinPage()->create([
        'workspace_id' => $this->workspace->id,
        'platform_user_id' => '123456',
        'username' => 'testcompany',
        'token_expires_at' => now()->addDays(60),
        'meta' => [
            'organization_id' => '123456',
            'admin_user_id' => 'user123',
            'admin_name' => 'John Doe',
        ],
    ]);

    $this->post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    $this->postPlatform = PostPlatform::factory()->create([
        'post_id' => $this->post->id,
        'social_account_id' => $this->socialAccount->id,
        'platform' => Platform::LinkedInPage,
        'content_type' => ContentType::LinkedInPagePost,
        'content' => 'Hello from our LinkedIn Page!',
    ]);

    $this->publisher = new LinkedInPagePublisher;
});

test('linkedin page publisher can publish text-only post', function () {
    Http::fake([
        'https://api.linkedin.com/rest/posts' => Http::response(null, 201, [
            'x-restli-id' => 'urn:li:share:1234567890',
        ]),
    ]);

    $result = $this->publisher->publish($this->postPlatform);

    expect($result)->toHaveKey('id');
    expect($result)->toHaveKey('url');
    expect($result['id'])->toBe('urn:li:share:1234567890');
    expect($result['url'])->toContain('linkedin.com/company/testcompany/posts/');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/rest/posts')
            && $request['author'] === 'urn:li:organization:123456'
            && $request['commentary'] === 'Hello from our LinkedIn Page!'
            && $request['visibility'] === 'PUBLIC';
    });
});

test('linkedin page publisher uses organization urn', function () {
    Http::fake([
        'https://api.linkedin.com/rest/posts' => Http::response(null, 201, [
            'x-restli-id' => 'urn:li:share:1234567890',
        ]),
    ]);

    $this->publisher->publish($this->postPlatform);

    Http::assertSent(function ($request) {
        return $request['author'] === 'urn:li:organization:123456';
    });
});

test('linkedin page publisher throws exception when organization id missing', function () {
    $this->socialAccount->update(['meta' => []]);

    expect(fn () => $this->publisher->publish($this->postPlatform))
        ->toThrow(Exception::class, 'LinkedIn Page organization ID not configured');
});

test('linkedin page publisher uses correct headers', function () {
    Http::fake([
        'https://api.linkedin.com/rest/posts' => Http::response(null, 201, [
            'x-restli-id' => 'urn:li:share:1234567890',
        ]),
    ]);

    $this->publisher->publish($this->postPlatform);

    Http::assertSent(function ($request) {
        return $request->hasHeader('Authorization')
            && $request->hasHeader('X-Restli-Protocol-Version')
            && $request->hasHeader('LinkedIn-Version')
            && str_starts_with($request->header('Authorization')[0], 'Bearer ');
    });
});

test('linkedin page publisher throws exception on api error', function () {
    Http::fake([
        'https://api.linkedin.com/rest/posts' => Http::response([
            'message' => 'Invalid request',
            'status' => 400,
        ], 400),
    ]);

    expect(fn () => $this->publisher->publish($this->postPlatform))
        ->toThrow(Exception::class);
});

test('linkedin page publisher throws token expired exception on auth error after retry', function () {
    Http::fake([
        'https://api.linkedin.com/rest/posts' => Http::response([
            'code' => 'EXPIRED_ACCESS_TOKEN',
            'message' => 'The token used in the request has expired',
        ], 401),
        'https://www.linkedin.com/oauth/v2/accessToken' => Http::response([
            'error' => 'invalid_grant',
            'error_description' => 'The refresh token is invalid',
        ], 400),
    ]);

    expect(fn () => $this->publisher->publish($this->postPlatform))
        ->toThrow(TokenExpiredException::class);
});

test('linkedin page publisher refreshes token when expired', function () {
    $this->socialAccount->update(['token_expires_at' => now()->subHour()]);

    Http::fake([
        'https://www.linkedin.com/oauth/v2/accessToken' => Http::response([
            'access_token' => 'new-access-token',
            'refresh_token' => 'new-refresh-token',
            'expires_in' => 5184000,
        ], 200),
        'https://api.linkedin.com/rest/posts' => Http::response(null, 201, [
            'x-restli-id' => 'urn:li:share:1234567890',
        ]),
    ]);

    $this->publisher->publish($this->postPlatform);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'oauth/v2/accessToken');
    });

    $this->socialAccount->refresh();
    expect($this->socialAccount->access_token)->toBe('new-access-token');
});

test('linkedin page publisher throws exception when no refresh token available', function () {
    $this->socialAccount->update([
        'token_expires_at' => now()->subHour(),
        'refresh_token' => null,
    ]);

    expect(fn () => $this->publisher->publish($this->postPlatform))
        ->toThrow(TokenExpiredException::class, 'No refresh token available for LinkedIn Page account');
});

test('linkedin page publisher handles empty content', function () {
    $this->postPlatform->update(['content' => '']);

    Http::fake([
        'https://api.linkedin.com/rest/posts' => Http::response(null, 201, [
            'x-restli-id' => 'urn:li:share:1234567890',
        ]),
    ]);

    $result = $this->publisher->publish($this->postPlatform);

    expect($result['id'])->toBe('urn:li:share:1234567890');

    Http::assertSent(function ($request) {
        return $request['commentary'] === '';
    });
});

test('linkedin page publisher throws exception for unsupported content type', function () {
    $this->postPlatform->update(['content_type' => ContentType::InstagramFeed]);

    expect(fn () => $this->publisher->publish($this->postPlatform))
        ->toThrow(Exception::class, 'Unsupported LinkedIn Page content type');
});

test('linkedin page publisher builds correct company url when username present', function () {
    Http::fake([
        'https://api.linkedin.com/rest/posts' => Http::response(null, 201, [
            'x-restli-id' => 'urn:li:share:1234567890',
        ]),
    ]);

    $result = $this->publisher->publish($this->postPlatform);

    expect($result['url'])->toContain('linkedin.com/company/testcompany/posts/');
});

test('linkedin page publisher builds feed url when username missing', function () {
    $this->socialAccount->update(['username' => null]);

    Http::fake([
        'https://api.linkedin.com/rest/posts' => Http::response(null, 201, [
            'x-restli-id' => 'urn:li:share:1234567890',
        ]),
    ]);

    $result = $this->publisher->publish($this->postPlatform);

    expect($result['url'])->toContain('linkedin.com/feed/update/urn:li:share:1234567890');
});
