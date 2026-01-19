<?php

use App\Enums\PostPlatform\ContentType;
use App\Enums\SocialAccount\Platform;
use App\Exceptions\TokenExpiredException;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\Workspace;
use App\Services\Social\LinkedInPublisher;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->workspace = Workspace::factory()->create();
    $this->socialAccount = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::LinkedIn,
        'platform_user_id' => 'linkedin-123',
        'access_token' => 'test-token',
        'token_expires_at' => now()->addDays(30),
    ]);
    $this->post = Post::factory()->create(['workspace_id' => $this->workspace->id]);
    $this->postPlatform = PostPlatform::factory()->create([
        'post_id' => $this->post->id,
        'social_account_id' => $this->socialAccount->id,
        'content' => 'Test LinkedIn post',
        'content_type' => ContentType::LinkedInPost,
    ]);
});

test('linkedin publisher publishes text only post', function () {
    Http::fake([
        '*/rest/posts' => Http::response(null, 201, ['x-restli-id' => 'urn:li:share:123456']),
    ]);

    $publisher = new LinkedInPublisher;
    $result = $publisher->publish($this->postPlatform);

    expect($result['id'])->toBe('urn:li:share:123456');
    expect($result['url'])->toBe('https://www.linkedin.com/feed/update/urn:li:share:123456');
});

test('linkedin publisher throws exception for carousel without images', function () {
    $this->postPlatform->update(['content_type' => ContentType::LinkedInCarousel]);

    $publisher = new LinkedInPublisher;

    expect(fn () => $publisher->publish($this->postPlatform))
        ->toThrow(\Exception::class, 'No valid images for LinkedIn carousel');
});

test('linkedin publisher throws token expired exception on oauth error', function () {
    Http::fake([
        '*' => Http::response([
            'code' => 'REVOKED_ACCESS_TOKEN',
            'message' => 'Token has been revoked',
        ], 401),
    ]);

    $publisher = new LinkedInPublisher;

    expect(fn () => $publisher->publish($this->postPlatform))
        ->toThrow(TokenExpiredException::class);
});

test('linkedin publisher throws token expired exception on expired token', function () {
    Http::fake([
        '*' => Http::response([
            'code' => 'EXPIRED_ACCESS_TOKEN',
            'message' => 'Token has expired',
        ], 401),
    ]);

    $publisher = new LinkedInPublisher;

    expect(fn () => $publisher->publish($this->postPlatform))
        ->toThrow(TokenExpiredException::class);
});

test('linkedin publisher throws token expired exception on invalid token', function () {
    Http::fake([
        '*' => Http::response([
            'code' => 'INVALID_ACCESS_TOKEN',
            'message' => 'Token is invalid',
        ], 401),
    ]);

    $publisher = new LinkedInPublisher;

    expect(fn () => $publisher->publish($this->postPlatform))
        ->toThrow(TokenExpiredException::class);
});

test('linkedin publisher refreshes token when expired', function () {
    $this->socialAccount->update([
        'token_expires_at' => now()->subHour(),
        'refresh_token' => 'refresh-token-123',
    ]);

    Http::fake([
        'https://www.linkedin.com/oauth/v2/accessToken' => Http::response([
            'access_token' => 'new-access-token',
            'refresh_token' => 'new-refresh-token',
            'expires_in' => 3600,
        ], 200),
        '*/rest/posts' => Http::response(null, 201, ['x-restli-id' => 'urn:li:share:123456']),
    ]);

    $publisher = new LinkedInPublisher;
    $result = $publisher->publish($this->postPlatform);

    expect($result['id'])->toBe('urn:li:share:123456');
    $this->socialAccount->refresh();
    expect($this->socialAccount->access_token)->toBe('new-access-token');
});

test('linkedin publisher throws exception when no refresh token', function () {
    $this->socialAccount->update([
        'token_expires_at' => now()->subHour(),
        'refresh_token' => null,
    ]);

    $publisher = new LinkedInPublisher;

    expect(fn () => $publisher->publish($this->postPlatform))
        ->toThrow(TokenExpiredException::class, 'No refresh token available');
});

test('linkedin publisher throws exception on api error', function () {
    Http::fake([
        '*' => Http::response([
            'code' => 'INVALID_REQUEST',
            'message' => 'Invalid request parameters',
        ], 400),
    ]);

    $publisher = new LinkedInPublisher;

    expect(fn () => $publisher->publish($this->postPlatform))
        ->toThrow(\Exception::class);
});

test('linkedin publisher throws exception for unsupported content type', function () {
    $this->postPlatform->update(['content_type' => ContentType::InstagramReel]);

    $publisher = new LinkedInPublisher;

    expect(fn () => $publisher->publish($this->postPlatform))
        ->toThrow(\Exception::class, 'Unsupported LinkedIn content type');
});

test('linkedin publisher handles 401 status as token error', function () {
    Http::fake([
        '*' => Http::response([
            'message' => 'Unauthorized',
        ], 401),
    ]);

    $publisher = new LinkedInPublisher;

    expect(fn () => $publisher->publish($this->postPlatform))
        ->toThrow(TokenExpiredException::class);
});

test('linkedin publisher handles token refresh failure', function () {
    $this->socialAccount->update([
        'token_expires_at' => now()->subHour(),
        'refresh_token' => 'refresh-token-123',
    ]);

    Http::fake([
        'https://www.linkedin.com/oauth/v2/accessToken' => Http::response([
            'code' => 'INVALID_REFRESH_TOKEN',
            'message' => 'Invalid refresh token',
        ], 400),
    ]);

    $publisher = new LinkedInPublisher;

    expect(fn () => $publisher->publish($this->postPlatform))
        ->toThrow(\Exception::class);
});

test('linkedin publisher refreshes token when expiring soon', function () {
    $this->socialAccount->update([
        'token_expires_at' => now()->addMinutes(5),
        'refresh_token' => 'refresh-token-123',
    ]);

    Http::fake([
        'https://www.linkedin.com/oauth/v2/accessToken' => Http::response([
            'access_token' => 'new-access-token',
            'refresh_token' => 'new-refresh-token',
            'expires_in' => 3600,
        ], 200),
        '*/rest/posts' => Http::response(null, 201, ['x-restli-id' => 'urn:li:share:123456']),
    ]);

    $publisher = new LinkedInPublisher;
    $result = $publisher->publish($this->postPlatform);

    expect($result['id'])->toBe('urn:li:share:123456');
    $this->socialAccount->refresh();
    expect($this->socialAccount->access_token)->toBe('new-access-token');
});

test('linkedin publisher handles empty post id header', function () {
    Http::fake([
        '*/rest/posts' => Http::response(null, 201),
    ]);

    $publisher = new LinkedInPublisher;
    $result = $publisher->publish($this->postPlatform);

    // When header is empty, id will be empty string or 'unknown'
    expect($result)->toHaveKey('id');
    expect($result)->toHaveKey('url');
});
