<?php

use App\Enums\SocialAccount\Platform;
use App\Enums\SocialAccount\Status;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);
    $this->workspace->members()->attach($this->user->id, ['role' => 'owner']);
});

test('instagram connect redirects to oauth provider', function () {
    Socialite::shouldReceive('driver')
        ->with('instagram')
        ->andReturn(Mockery::mock([
            'scopes' => Mockery::self(),
            'redirect' => Mockery::mock([
                'getTargetUrl' => 'https://www.instagram.com/oauth/authorize?test=1',
            ]),
        ]));

    $response = $this->actingAs($this->user)
        ->withHeader('X-Inertia', 'true')
        ->get(route('social.instagram.connect'));

    $response->assertStatus(409);

    expect(session('social_connect_workspace'))->toBe($this->workspace->id);
});

test('instagram oauth callback creates account', function () {
    session([
        'social_connect_workspace' => $this->workspace->id,
    ]);

    $socialiteUser = Mockery::mock(SocialiteUser::class);
    $socialiteUser->shouldReceive('getId')->andReturn('12345678');
    $socialiteUser->shouldReceive('getNickname')->andReturn('testuser');
    $socialiteUser->shouldReceive('getName')->andReturn('Test User');
    $socialiteUser->shouldReceive('getAvatar')->andReturn(null);
    $socialiteUser->token = 'test-access-token';
    $socialiteUser->refreshToken = 'test-refresh-token';
    $socialiteUser->expiresIn = 5184000;
    $socialiteUser->user = ['account_type' => 'BUSINESS'];

    Socialite::shouldReceive('driver')
        ->with('instagram')
        ->andReturn(Mockery::mock([
            'user' => $socialiteUser,
        ]));

    $response = $this->actingAs($this->user)->get(route('social.instagram.callback'));

    $response->assertOk();
    $response->assertViewIs('auth.social-callback');
    $response->assertViewHas('success', true);

    $this->assertDatabaseHas('social_accounts', [
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::Instagram->value,
        'platform_user_id' => '12345678',
        'username' => 'testuser',
        'status' => Status::Connected->value,
    ]);
});

test('instagram callback fails with expired session', function () {
    $response = $this->actingAs($this->user)->get(route('social.instagram.callback'));

    $response->assertOk();
    $response->assertViewHas('success', false);
    $response->assertViewHas('message', 'Session expired. Please try again.');
});

test('user cannot connect instagram if already connected', function () {
    SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::Instagram,
        'platform_user_id' => '12345678',
        'status' => Status::Connected,
    ]);

    session([
        'social_connect_workspace' => $this->workspace->id,
    ]);

    $socialiteUser = Mockery::mock(SocialiteUser::class);
    $socialiteUser->shouldReceive('getId')->andReturn('87654321');
    $socialiteUser->shouldReceive('getNickname')->andReturn('newuser');
    $socialiteUser->shouldReceive('getName')->andReturn('New User');
    $socialiteUser->shouldReceive('getAvatar')->andReturn(null);
    $socialiteUser->token = 'new-access-token';
    $socialiteUser->refreshToken = 'new-refresh-token';
    $socialiteUser->expiresIn = 5184000;
    $socialiteUser->user = ['account_type' => 'BUSINESS'];

    Socialite::shouldReceive('driver')
        ->with('instagram')
        ->andReturn(Mockery::mock([
            'user' => $socialiteUser,
        ]));

    $response = $this->actingAs($this->user)->get(route('social.instagram.callback'));

    $response->assertOk();
    $response->assertViewHas('success', false);
    $response->assertViewHas('message', 'This platform is already connected.');
});

test('user can reconnect disconnected instagram account', function () {
    $existingAccount = SocialAccount::factory()->disconnected()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::Instagram,
        'platform_user_id' => '12345678',
    ]);

    session([
        'social_connect_workspace' => $this->workspace->id,
        'social_reconnect_id' => $existingAccount->id,
    ]);

    $socialiteUser = Mockery::mock(SocialiteUser::class);
    $socialiteUser->shouldReceive('getId')->andReturn('12345678');
    $socialiteUser->shouldReceive('getNickname')->andReturn('testuser');
    $socialiteUser->shouldReceive('getName')->andReturn('Test User');
    $socialiteUser->shouldReceive('getAvatar')->andReturn(null);
    $socialiteUser->token = 'new-access-token';
    $socialiteUser->refreshToken = 'new-refresh-token';
    $socialiteUser->expiresIn = 5184000;
    $socialiteUser->user = ['account_type' => 'BUSINESS'];

    Socialite::shouldReceive('driver')
        ->with('instagram')
        ->andReturn(Mockery::mock([
            'user' => $socialiteUser,
        ]));

    $response = $this->actingAs($this->user)->get(route('social.instagram.callback'));

    $response->assertOk();
    $response->assertViewHas('success', true);

    $existingAccount->refresh();
    expect($existingAccount->status)->toBe(Status::Connected);
    expect($existingAccount->access_token)->toBe('new-access-token');
});

test('instagram callback handles oauth errors gracefully', function () {
    session([
        'social_connect_workspace' => $this->workspace->id,
    ]);

    $mock = Mockery::mock();
    $mock->shouldReceive('user')->andThrow(new \Exception('OAuth error'));

    Socialite::shouldReceive('driver')
        ->with('instagram')
        ->andReturn($mock);

    $response = $this->actingAs($this->user)->get(route('social.instagram.callback'));

    $response->assertOk();
    $response->assertViewHas('success', false);
    $response->assertViewHas('message', 'Error connecting account. Please try again.');
});

test('instagram connect redirects to create workspace if none exists', function () {
    $this->user->update(['current_workspace_id' => null]);

    $response = $this->actingAs($this->user)->get(route('social.instagram.connect'));

    $response->assertRedirect(route('workspaces.create'));
});
