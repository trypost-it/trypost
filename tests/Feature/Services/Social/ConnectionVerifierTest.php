<?php

use App\Exceptions\TokenExpiredException;
use App\Models\SocialAccount;
use App\Services\Social\ConnectionVerifier;
use Illuminate\Support\Facades\Http;

test('verifies account without refresh when token is not expired', function () {
    Http::fake([
        'api.linkedin.com/*' => Http::response(['sub' => '123'], 200),
    ]);

    $account = SocialAccount::factory()->linkedin()->create([
        'token_expires_at' => now()->addDays(30),
    ]);

    $verifier = new ConnectionVerifier;
    $result = $verifier->verify($account);

    expect($result)->toBeTrue();

    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => str_contains($request->url(), 'api.linkedin.com/rest/userinfo'));
});

test('refreshes linkedin token before verifying when expired', function () {
    Http::fake([
        'www.linkedin.com/oauth/v2/accessToken' => Http::response([
            'access_token' => 'new_token',
            'refresh_token' => 'new_refresh_token',
            'expires_in' => 5184000,
        ], 200),
        'api.linkedin.com/*' => Http::response(['sub' => '123'], 200),
    ]);

    $account = SocialAccount::factory()->linkedin()->create([
        'token_expires_at' => now()->subHour(),
        'refresh_token' => 'old_refresh_token',
    ]);

    $verifier = new ConnectionVerifier;
    $result = $verifier->verify($account);

    expect($result)->toBeTrue();
    expect($account->fresh()->token_expires_at)->toBeGreaterThan(now());

    Http::assertSent(fn ($request) => str_contains($request->url(), 'linkedin.com/oauth/v2/accessToken'));
});

test('refreshes x token before verifying when expired', function () {
    Http::fake([
        'api.x.com/2/oauth2/token' => Http::response([
            'access_token' => 'new_token',
            'refresh_token' => 'new_refresh_token',
            'expires_in' => 7200,
        ], 200),
        'api.x.com/2/users/me' => Http::response(['data' => ['id' => '123']], 200),
    ]);

    $account = SocialAccount::factory()->x()->create([
        'token_expires_at' => now()->subHour(),
        'refresh_token' => 'old_refresh_token',
    ]);

    $verifier = new ConnectionVerifier;
    $result = $verifier->verify($account);

    expect($result)->toBeTrue();
    expect($account->fresh()->token_expires_at)->toBeGreaterThan(now());

    Http::assertSent(fn ($request) => str_contains($request->url(), 'api.x.com/2/oauth2/token'));
});

test('refreshes bluesky token before verifying when expired', function () {
    Http::fake([
        'bsky.social/xrpc/com.atproto.server.refreshSession' => Http::response([
            'accessJwt' => 'new_access_token',
            'refreshJwt' => 'new_refresh_token',
        ], 200),
        'bsky.social/xrpc/app.bsky.actor.getProfile*' => Http::response(['did' => 'did:plc:123'], 200),
    ]);

    $account = SocialAccount::factory()->bluesky()->create([
        'token_expires_at' => now()->subHour(),
        'refresh_token' => 'old_refresh_token',
    ]);

    $verifier = new ConnectionVerifier;
    $result = $verifier->verify($account);

    expect($result)->toBeTrue();
    expect($account->fresh()->token_expires_at)->toBeGreaterThan(now());

    Http::assertSent(fn ($request) => str_contains($request->url(), 'refreshSession'));
});

test('refreshes youtube token before verifying when expired', function () {
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response([
            'access_token' => 'new_token',
            'expires_in' => 3600,
        ], 200),
        'www.googleapis.com/youtube/*' => Http::response(['items' => []], 200),
    ]);

    $account = SocialAccount::factory()->youtube()->create([
        'token_expires_at' => now()->subHour(),
        'refresh_token' => 'old_refresh_token',
    ]);

    $verifier = new ConnectionVerifier;
    $result = $verifier->verify($account);

    expect($result)->toBeTrue();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'oauth2.googleapis.com/token'));
});

test('refreshes tiktok token before verifying when expired', function () {
    Http::fake([
        'open.tiktokapis.com/v2/oauth/token/' => Http::response([
            'access_token' => 'new_token',
            'refresh_token' => 'new_refresh_token',
            'expires_in' => 86400,
        ], 200),
        'open.tiktokapis.com/v2/user/info/*' => Http::response(['data' => ['user' => []]], 200),
    ]);

    $account = SocialAccount::factory()->tiktok()->create([
        'token_expires_at' => now()->subHour(),
        'refresh_token' => 'old_refresh_token',
    ]);

    $verifier = new ConnectionVerifier;
    $result = $verifier->verify($account);

    expect($result)->toBeTrue();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'tiktokapis.com/v2/oauth/token'));
});

test('refreshes pinterest token before verifying when expired', function () {
    Http::fake([
        'api.pinterest.com/v5/oauth/token' => Http::response([
            'access_token' => 'new_token',
            'refresh_token' => 'new_refresh_token',
            'expires_in' => 86400,
        ], 200),
        'api.pinterest.com/v5/user_account' => Http::response(['username' => 'test'], 200),
    ]);

    $account = SocialAccount::factory()->pinterest()->create([
        'token_expires_at' => now()->subHour(),
        'refresh_token' => 'old_refresh_token',
    ]);

    $verifier = new ConnectionVerifier;
    $result = $verifier->verify($account);

    expect($result)->toBeTrue();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'pinterest.com/v5/oauth/token'));
});

test('refreshes threads token before verifying when expired', function () {
    Http::fake([
        'graph.threads.net/refresh_access_token*' => Http::response([
            'access_token' => 'new_token',
            'expires_in' => 5184000,
        ], 200),
        'graph.threads.net/v1.0/me*' => Http::response(['id' => '123', 'username' => 'test'], 200),
    ]);

    $account = SocialAccount::factory()->threads()->create([
        'token_expires_at' => now()->subHour(),
    ]);

    $verifier = new ConnectionVerifier;
    $result = $verifier->verify($account);

    expect($result)->toBeTrue();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'refresh_access_token'));
});

test('throws exception when linkedin refresh fails', function () {
    Http::fake([
        'www.linkedin.com/oauth/v2/accessToken' => Http::response(['error' => 'invalid_grant'], 400),
    ]);

    $account = SocialAccount::factory()->linkedin()->create([
        'token_expires_at' => now()->subHour(),
        'refresh_token' => 'old_refresh_token',
    ]);

    $verifier = new ConnectionVerifier;

    expect(fn () => $verifier->verify($account))
        ->toThrow(TokenExpiredException::class, 'Failed to refresh LinkedIn token');
});

test('throws exception when x refresh fails', function () {
    Http::fake([
        'api.x.com/2/oauth2/token' => Http::response(['error' => 'invalid_grant'], 400),
    ]);

    $account = SocialAccount::factory()->x()->create([
        'token_expires_at' => now()->subHour(),
        'refresh_token' => 'old_refresh_token',
    ]);

    $verifier = new ConnectionVerifier;

    expect(fn () => $verifier->verify($account))
        ->toThrow(TokenExpiredException::class, 'Failed to refresh X token');
});

test('does not refresh facebook token as it uses long-lived tokens', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['id' => '123', 'name' => 'Test'], 200),
    ]);

    $account = SocialAccount::factory()->facebook()->create([
        'token_expires_at' => now()->subHour(),
    ]);

    $verifier = new ConnectionVerifier;
    $result = $verifier->verify($account);

    expect($result)->toBeTrue();

    // Should only call the verify endpoint, no refresh
    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => str_contains($request->url(), 'graph.facebook.com'));
});

test('does not refresh instagram token as it uses long-lived tokens', function () {
    Http::fake([
        'graph.instagram.com/*' => Http::response(['id' => '123', 'username' => 'test'], 200),
    ]);

    $account = SocialAccount::factory()->instagram()->create([
        'token_expires_at' => now()->subHour(),
    ]);

    $verifier = new ConnectionVerifier;
    $result = $verifier->verify($account);

    expect($result)->toBeTrue();

    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => str_contains($request->url(), 'graph.instagram.com'));
});

test('refreshes token when expiring soon', function () {
    Http::fake([
        'www.linkedin.com/oauth/v2/accessToken' => Http::response([
            'access_token' => 'new_token',
            'refresh_token' => 'new_refresh_token',
            'expires_in' => 5184000,
        ], 200),
        'api.linkedin.com/*' => Http::response(['sub' => '123'], 200),
    ]);

    // Token expires in 30 minutes (less than 1 hour threshold)
    $account = SocialAccount::factory()->linkedin()->create([
        'token_expires_at' => now()->addMinutes(30),
        'refresh_token' => 'old_refresh_token',
    ]);

    $verifier = new ConnectionVerifier;
    $result = $verifier->verify($account);

    expect($result)->toBeTrue();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'linkedin.com/oauth/v2/accessToken'));
});
