<?php

declare(strict_types=1);

use App\Enums\SocialAccount\Platform;
use App\Enums\SocialAccount\Status;
use App\Enums\UserWorkspace\Role;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Owner->value]);
});

test('bluesky connect page can be rendered', function () {
    $response = $this->actingAs($this->user)->get(route('app.social.bluesky.connect'));

    $response->assertOk();
});

test('user can connect bluesky account with valid credentials', function () {
    Http::fake([
        'https://bsky.social/xrpc/com.atproto.server.createSession' => Http::response([
            'did' => 'did:plc:testuser123',
            'handle' => 'testuser.bsky.social',
            'accessJwt' => 'test-access-token',
            'refreshJwt' => 'test-refresh-token',
        ], 200),
        'https://bsky.social/xrpc/app.bsky.actor.getProfile*' => Http::response([
            'did' => 'did:plc:testuser123',
            'handle' => 'testuser.bsky.social',
            'displayName' => 'Test User',
            'avatar' => null,
        ], 200),
    ]);

    $response = $this->actingAs($this->user)->post(route('app.social.bluesky.store'), [
        'identifier' => 'testuser.bsky.social',
        'password' => 'xxxx-xxxx-xxxx-xxxx',
    ]);

    $response->assertOk();
    $response->assertViewIs('auth.social-callback');
    $response->assertViewHas('success', true);

    $this->assertDatabaseHas('social_accounts', [
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::Bluesky->value,
        'platform_user_id' => 'did:plc:testuser123',
        'username' => 'testuser.bsky.social',
        'status' => Status::Connected->value,
    ]);
});

test('user cannot connect bluesky with invalid credentials', function () {
    Http::fake([
        'https://bsky.social/xrpc/com.atproto.server.createSession' => Http::response([
            'error' => 'AuthenticationRequired',
            'message' => 'Invalid identifier or password',
        ], 401),
    ]);

    $response = $this->actingAs($this->user)->post(route('app.social.bluesky.store'), [
        'identifier' => 'testuser.bsky.social',
        'password' => 'wrong-password',
    ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors('password');

    $this->assertDatabaseMissing('social_accounts', [
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::Bluesky->value,
    ]);
});

test('user can connect multiple bluesky accounts', function () {
    SocialAccount::factory()->bluesky()->create([
        'workspace_id' => $this->workspace->id,
        'platform_user_id' => 'did:plc:existing123',
    ]);

    Http::fake([
        'https://bsky.social/xrpc/com.atproto.server.createSession' => Http::response([
            'did' => 'did:plc:newuser456',
            'handle' => 'newuser.bsky.social',
            'accessJwt' => 'test-access-token',
            'refreshJwt' => 'test-refresh-token',
        ], 200),
        'https://bsky.social/xrpc/app.bsky.actor.getProfile*' => Http::response([
            'did' => 'did:plc:newuser456',
            'handle' => 'newuser.bsky.social',
            'displayName' => 'New User',
        ], 200),
    ]);

    $response = $this->actingAs($this->user)->post(route('app.social.bluesky.store'), [
        'identifier' => 'newuser.bsky.social',
        'password' => 'xxxx-xxxx-xxxx-xxxx',
    ]);

    $response->assertOk();
    $response->assertViewHas('success', true);

    expect($this->workspace->socialAccounts()->where('platform', Platform::Bluesky)->count())->toBe(2);
});

test('bluesky connection validates required fields', function () {
    $response = $this->actingAs($this->user)->post(route('app.social.bluesky.store'), [
        'identifier' => '',
        'password' => '',
    ]);

    $response->assertSessionHasErrors(['identifier', 'password']);
});
