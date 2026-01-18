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

test('mastodon connect page can be rendered', function () {
    $response = $this->actingAs($this->user)->get(route('social.mastodon.connect'));

    $response->assertOk();
});

test('user can initiate mastodon oauth flow', function () {
    Http::fake([
        'https://mastodon.social/api/v1/apps' => Http::response([
            'client_id' => 'test-client-id',
            'client_secret' => 'test-client-secret',
            'id' => '12345',
            'name' => config('app.name'),
            'redirect_uri' => route('social.mastodon.callback'),
        ], 200),
    ]);

    $response = $this->actingAs($this->user)
        ->withHeader('X-Inertia', 'true')
        ->post(route('social.mastodon.authorize'), [
            'instance' => 'https://mastodon.social',
        ]);

    $response->assertStatus(409); // Inertia::location returns 409 with X-Inertia header

    expect(session('mastodon_instance'))->toBe('https://mastodon.social');
    expect(session('mastodon_client_id'))->toBe('test-client-id');
    expect(session('mastodon_client_secret'))->toBe('test-client-secret');
});

test('user cannot connect to invalid mastodon instance', function () {
    Http::fake([
        'https://invalid-instance.com/api/v1/apps' => Http::response([], 404),
    ]);

    $response = $this->actingAs($this->user)->post(route('social.mastodon.authorize'), [
        'instance' => 'https://invalid-instance.com',
    ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors('instance');
});

test('mastodon oauth callback creates account', function () {
    // Setup session as if OAuth flow was initiated
    session([
        'mastodon_instance' => 'https://mastodon.social',
        'mastodon_client_id' => 'test-client-id',
        'mastodon_client_secret' => 'test-client-secret',
        'mastodon_oauth_state' => 'test-state',
        'social_connect_workspace' => $this->workspace->id,
    ]);

    Http::fake([
        'https://mastodon.social/oauth/token' => Http::response([
            'access_token' => 'test-access-token',
            'token_type' => 'Bearer',
            'scope' => 'read:accounts write:statuses write:media',
            'created_at' => time(),
        ], 200),
        'https://mastodon.social/api/v1/accounts/verify_credentials' => Http::response([
            'id' => '123456789',
            'username' => 'testuser',
            'acct' => 'testuser',
            'display_name' => 'Test User',
            'avatar' => null,
        ], 200),
    ]);

    $response = $this->actingAs($this->user)->get(route('social.mastodon.callback', [
        'code' => 'test-auth-code',
        'state' => 'test-state',
    ]));

    $response->assertOk();
    $response->assertViewIs('auth.social-callback');
    $response->assertViewHas('success', true);

    $this->assertDatabaseHas('social_accounts', [
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::Mastodon->value,
        'platform_user_id' => '123456789',
        'username' => 'testuser',
        'status' => Status::Connected->value,
    ]);
});

test('mastodon callback fails with invalid state', function () {
    session([
        'mastodon_instance' => 'https://mastodon.social',
        'mastodon_client_id' => 'test-client-id',
        'mastodon_client_secret' => 'test-client-secret',
        'mastodon_oauth_state' => 'correct-state',
        'social_connect_workspace' => $this->workspace->id,
    ]);

    $response = $this->actingAs($this->user)->get(route('social.mastodon.callback', [
        'code' => 'test-auth-code',
        'state' => 'wrong-state',
    ]));

    $response->assertOk();
    $response->assertViewHas('success', false);

    $this->assertDatabaseMissing('social_accounts', [
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::Mastodon->value,
    ]);
});

test('mastodon callback fails with expired session', function () {
    // No session data - simulating expired session

    $response = $this->actingAs($this->user)->get(route('social.mastodon.callback', [
        'code' => 'test-auth-code',
        'state' => 'test-state',
    ]));

    $response->assertOk();
    $response->assertViewHas('success', false);
    $response->assertViewHas('message', 'Session expired. Please try again.');
});

test('user cannot connect mastodon if already connected', function () {
    SocialAccount::factory()->mastodon()->create([
        'workspace_id' => $this->workspace->id,
        'platform_user_id' => '123456789',
    ]);

    session([
        'mastodon_instance' => 'https://mastodon.social',
        'mastodon_client_id' => 'test-client-id',
        'mastodon_client_secret' => 'test-client-secret',
        'mastodon_oauth_state' => 'test-state',
        'social_connect_workspace' => $this->workspace->id,
    ]);

    Http::fake([
        'https://mastodon.social/oauth/token' => Http::response([
            'access_token' => 'new-access-token',
            'token_type' => 'Bearer',
        ], 200),
        'https://mastodon.social/api/v1/accounts/verify_credentials' => Http::response([
            'id' => '987654321',
            'username' => 'anotheruser',
            'acct' => 'anotheruser',
            'display_name' => 'Another User',
        ], 200),
    ]);

    $response = $this->actingAs($this->user)->get(route('social.mastodon.callback', [
        'code' => 'test-auth-code',
        'state' => 'test-state',
    ]));

    $response->assertOk();
    $response->assertViewHas('success', false);
    $response->assertViewHas('message', 'Mastodon is already connected.');
});

test('user can reconnect disconnected mastodon account', function () {
    $existingAccount = SocialAccount::factory()->mastodon()->disconnected()->create([
        'workspace_id' => $this->workspace->id,
        'platform_user_id' => '123456789',
    ]);

    session([
        'mastodon_instance' => 'https://mastodon.social',
        'mastodon_client_id' => 'test-client-id',
        'mastodon_client_secret' => 'test-client-secret',
        'mastodon_oauth_state' => 'test-state',
        'social_connect_workspace' => $this->workspace->id,
    ]);

    Http::fake([
        'https://mastodon.social/oauth/token' => Http::response([
            'access_token' => 'new-access-token',
            'token_type' => 'Bearer',
        ], 200),
        'https://mastodon.social/api/v1/accounts/verify_credentials' => Http::response([
            'id' => '123456789',
            'username' => 'testuser',
            'acct' => 'testuser',
            'display_name' => 'Test User',
        ], 200),
    ]);

    $response = $this->actingAs($this->user)->get(route('social.mastodon.callback', [
        'code' => 'test-auth-code',
        'state' => 'test-state',
    ]));

    $response->assertOk();
    $response->assertViewHas('success', true);

    $existingAccount->refresh();
    expect($existingAccount->status)->toBe(Status::Connected);
    expect($existingAccount->access_token)->toBe('new-access-token');
});

test('mastodon connection validates instance url', function () {
    $response = $this->actingAs($this->user)->post(route('social.mastodon.authorize'), [
        'instance' => 'not-a-valid-url',
    ]);

    $response->assertSessionHasErrors('instance');
});

test('mastodon works with custom instances', function () {
    Http::fake([
        'https://techhub.social/api/v1/apps' => Http::response([
            'client_id' => 'custom-client-id',
            'client_secret' => 'custom-client-secret',
            'id' => '67890',
            'name' => config('app.name'),
        ], 200),
    ]);

    $response = $this->actingAs($this->user)
        ->withHeader('X-Inertia', 'true')
        ->post(route('social.mastodon.authorize'), [
            'instance' => 'https://techhub.social',
        ]);

    $response->assertStatus(409); // Inertia::location with X-Inertia header

    expect(session('mastodon_instance'))->toBe('https://techhub.social');
});
