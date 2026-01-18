<?php

use App\Enums\SocialAccount\Platform;
use App\Enums\SocialAccount\Status;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);
    $this->workspace->members()->attach($this->user->id, ['role' => 'owner']);
});

test('threads connect redirects to oauth', function () {
    $response = $this->actingAs($this->user)
        ->withHeader('X-Inertia', 'true')
        ->get(route('social.threads.connect'));

    $response->assertStatus(409); // Inertia::location returns 409 with X-Inertia header

    expect(session('social_connect_workspace'))->toBe($this->workspace->id);
    expect(session('threads_oauth_state'))->not->toBeNull();
});

test('threads oauth callback creates account', function () {
    $state = bin2hex(random_bytes(16));

    session([
        'social_connect_workspace' => $this->workspace->id,
        'threads_oauth_state' => $state,
    ]);

    Http::fake([
        'https://graph.threads.net/oauth/access_token' => Http::response([
            'access_token' => 'short-lived-token',
            'user_id' => '123456789',
        ], 200),
        'https://graph.threads.net/access_token*' => Http::response([
            'access_token' => 'long-lived-token',
            'expires_in' => 5184000, // 60 days
        ], 200),
        'https://graph.threads.net/v1.0/123456789*' => Http::response([
            'id' => '123456789',
            'username' => 'testuser',
            'name' => 'Test User',
            'threads_profile_picture_url' => null,
        ], 200),
    ]);

    $response = $this->actingAs($this->user)->get(route('social.threads.callback', [
        'code' => 'test-auth-code',
        'state' => $state,
    ]));

    $response->assertOk();
    $response->assertViewIs('auth.social-callback');
    $response->assertViewHas('success', true);

    $this->assertDatabaseHas('social_accounts', [
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::Threads->value,
        'platform_user_id' => '123456789',
        'username' => 'testuser',
        'status' => Status::Connected->value,
    ]);
});

test('threads callback fails with invalid state', function () {
    session([
        'social_connect_workspace' => $this->workspace->id,
        'threads_oauth_state' => 'correct-state',
    ]);

    $response = $this->actingAs($this->user)->get(route('social.threads.callback', [
        'code' => 'test-auth-code',
        'state' => 'wrong-state',
    ]));

    $response->assertOk();
    $response->assertViewHas('success', false);
    $response->assertViewHas('message', 'Invalid state. Please try again.');

    $this->assertDatabaseMissing('social_accounts', [
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::Threads->value,
    ]);
});

test('threads callback fails with expired session', function () {
    // No session data - simulating expired session

    $response = $this->actingAs($this->user)->get(route('social.threads.callback', [
        'code' => 'test-auth-code',
        'state' => 'test-state',
    ]));

    $response->assertOk();
    $response->assertViewHas('success', false);
    $response->assertViewHas('message', 'Session expired. Please try again.');
});

test('user cannot connect threads if already connected', function () {
    SocialAccount::factory()->threads()->create([
        'workspace_id' => $this->workspace->id,
        'platform_user_id' => '123456789',
    ]);

    $state = bin2hex(random_bytes(16));

    session([
        'social_connect_workspace' => $this->workspace->id,
        'threads_oauth_state' => $state,
    ]);

    Http::fake([
        'https://graph.threads.net/oauth/access_token' => Http::response([
            'access_token' => 'new-token',
            'user_id' => '987654321',
        ], 200),
        'https://graph.threads.net/access_token*' => Http::response([
            'access_token' => 'long-lived-token',
            'expires_in' => 5184000,
        ], 200),
        'https://graph.threads.net/v1.0/987654321*' => Http::response([
            'id' => '987654321',
            'username' => 'anotheruser',
            'name' => 'Another User',
        ], 200),
    ]);

    $response = $this->actingAs($this->user)->get(route('social.threads.callback', [
        'code' => 'test-auth-code',
        'state' => $state,
    ]));

    $response->assertOk();
    $response->assertViewHas('success', false);
    $response->assertViewHas('message', 'This platform is already connected.');
});

test('user can reconnect disconnected threads account', function () {
    $existingAccount = SocialAccount::factory()->threads()->disconnected()->create([
        'workspace_id' => $this->workspace->id,
        'platform_user_id' => '123456789',
    ]);

    $state = bin2hex(random_bytes(16));

    session([
        'social_connect_workspace' => $this->workspace->id,
        'threads_oauth_state' => $state,
        'social_reconnect_id' => $existingAccount->id,
    ]);

    Http::fake([
        'https://graph.threads.net/oauth/access_token' => Http::response([
            'access_token' => 'new-short-token',
            'user_id' => '123456789',
        ], 200),
        'https://graph.threads.net/access_token*' => Http::response([
            'access_token' => 'new-long-lived-token',
            'expires_in' => 5184000,
        ], 200),
        'https://graph.threads.net/v1.0/123456789*' => Http::response([
            'id' => '123456789',
            'username' => 'testuser',
            'name' => 'Test User',
        ], 200),
    ]);

    $response = $this->actingAs($this->user)->get(route('social.threads.callback', [
        'code' => 'test-auth-code',
        'state' => $state,
    ]));

    $response->assertOk();
    $response->assertViewHas('success', true);

    $existingAccount->refresh();
    expect($existingAccount->status)->toBe(Status::Connected);
    expect($existingAccount->access_token)->toBe('new-long-lived-token');
});

test('threads callback handles token exchange failure', function () {
    $state = bin2hex(random_bytes(16));

    session([
        'social_connect_workspace' => $this->workspace->id,
        'threads_oauth_state' => $state,
    ]);

    Http::fake([
        'https://graph.threads.net/oauth/access_token' => Http::response([
            'error' => 'invalid_grant',
            'error_description' => 'The authorization code has expired.',
        ], 400),
    ]);

    $response = $this->actingAs($this->user)->get(route('social.threads.callback', [
        'code' => 'expired-auth-code',
        'state' => $state,
    ]));

    $response->assertOk();
    $response->assertViewHas('success', false);
    $response->assertViewHas('message', 'Error connecting account. Please try again.');
});
