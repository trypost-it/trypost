<?php

use App\Enums\SocialAccount\Platform;
use App\Enums\SocialAccount\Status;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);
    $this->workspace->members()->attach($this->user->id, ['role' => 'owner']);
});

test('linkedin connect redirects to oauth provider', function () {
    Socialite::shouldReceive('driver')
        ->with('linkedin')
        ->andReturn(Mockery::mock([
            'scopes' => Mockery::self(),
            'redirect' => Mockery::mock([
                'getTargetUrl' => 'https://www.linkedin.com/oauth/v2/authorization?test=1',
            ]),
        ]));

    $response = $this->actingAs($this->user)
        ->withHeader('X-Inertia', 'true')
        ->get(route('social.linkedin.connect'));

    $response->assertStatus(409); // Inertia::location returns 409 with X-Inertia header

    expect(session('social_connect_workspace'))->toBe($this->workspace->id);
});

test('linkedin oauth callback creates account', function () {
    session([
        'social_connect_workspace' => $this->workspace->id,
    ]);

    $socialiteUser = Mockery::mock(SocialiteUser::class);
    $socialiteUser->shouldReceive('getId')->andReturn('abc123xyz');
    $socialiteUser->shouldReceive('getNickname')->andReturn(null);
    $socialiteUser->shouldReceive('getName')->andReturn('John Doe');
    $socialiteUser->shouldReceive('getAvatar')->andReturn(null);
    $socialiteUser->token = 'test-access-token';
    $socialiteUser->refreshToken = 'test-refresh-token';
    $socialiteUser->expiresIn = 5184000; // 60 days
    $socialiteUser->approvedScopes = ['openid', 'profile', 'email', 'w_member_social'];

    Socialite::shouldReceive('driver')
        ->with('linkedin')
        ->andReturn(Mockery::mock([
            'user' => $socialiteUser,
        ]));

    Http::fake([
        'https://api.linkedin.com/v2/me*' => Http::response([
            'id' => 'abc123xyz',
            'vanityName' => 'johndoe',
            'localizedFirstName' => 'John',
            'localizedLastName' => 'Doe',
        ], 200),
    ]);

    $response = $this->actingAs($this->user)->get(route('social.linkedin.callback'));

    $response->assertOk();
    $response->assertViewIs('auth.social-callback');
    $response->assertViewHas('success', true);

    $this->assertDatabaseHas('social_accounts', [
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::LinkedIn->value,
        'platform_user_id' => 'abc123xyz',
        'username' => 'johndoe',
        'status' => Status::Connected->value,
    ]);
});

test('linkedin callback fails with expired session', function () {
    // No session data - simulating expired session

    $response = $this->actingAs($this->user)->get(route('social.linkedin.callback'));

    $response->assertOk();
    $response->assertViewHas('success', false);
    $response->assertViewHas('message', 'Session expired. Please try again.');
});

test('user cannot connect linkedin if already connected', function () {
    SocialAccount::factory()->linkedin()->create([
        'workspace_id' => $this->workspace->id,
        'platform_user_id' => 'abc123xyz',
    ]);

    session([
        'social_connect_workspace' => $this->workspace->id,
    ]);

    $socialiteUser = Mockery::mock(SocialiteUser::class);
    $socialiteUser->shouldReceive('getId')->andReturn('xyz789abc');
    $socialiteUser->shouldReceive('getNickname')->andReturn(null);
    $socialiteUser->shouldReceive('getName')->andReturn('Jane Doe');
    $socialiteUser->shouldReceive('getAvatar')->andReturn(null);
    $socialiteUser->token = 'new-access-token';
    $socialiteUser->refreshToken = 'new-refresh-token';
    $socialiteUser->expiresIn = 5184000;

    Socialite::shouldReceive('driver')
        ->with('linkedin')
        ->andReturn(Mockery::mock([
            'user' => $socialiteUser,
        ]));

    $response = $this->actingAs($this->user)->get(route('social.linkedin.callback'));

    $response->assertOk();
    $response->assertViewHas('success', false);
    $response->assertViewHas('message', 'This platform is already connected.');
});

test('user can reconnect disconnected linkedin account', function () {
    $existingAccount = SocialAccount::factory()->linkedin()->disconnected()->create([
        'workspace_id' => $this->workspace->id,
        'platform_user_id' => 'abc123xyz',
    ]);

    session([
        'social_connect_workspace' => $this->workspace->id,
    ]);

    $socialiteUser = Mockery::mock(SocialiteUser::class);
    $socialiteUser->shouldReceive('getId')->andReturn('abc123xyz');
    $socialiteUser->shouldReceive('getNickname')->andReturn(null);
    $socialiteUser->shouldReceive('getName')->andReturn('John Doe');
    $socialiteUser->shouldReceive('getAvatar')->andReturn(null);
    $socialiteUser->token = 'new-access-token';
    $socialiteUser->refreshToken = 'new-refresh-token';
    $socialiteUser->expiresIn = 5184000;
    $socialiteUser->approvedScopes = ['openid', 'profile', 'email', 'w_member_social'];

    Socialite::shouldReceive('driver')
        ->with('linkedin')
        ->andReturn(Mockery::mock([
            'user' => $socialiteUser,
        ]));

    Http::fake([
        'https://api.linkedin.com/v2/me*' => Http::response([
            'vanityName' => 'johndoe',
        ], 200),
    ]);

    $response = $this->actingAs($this->user)->get(route('social.linkedin.callback'));

    $response->assertOk();
    $response->assertViewHas('success', true);

    $existingAccount->refresh();
    expect($existingAccount->status)->toBe(Status::Connected);
    expect($existingAccount->access_token)->toBe('new-access-token');
});

test('linkedin callback handles oauth errors gracefully', function () {
    session([
        'social_connect_workspace' => $this->workspace->id,
    ]);

    $mock = Mockery::mock();
    $mock->shouldReceive('user')->andThrow(new \Exception('OAuth error'));

    Socialite::shouldReceive('driver')
        ->with('linkedin')
        ->andReturn($mock);

    $response = $this->actingAs($this->user)->get(route('social.linkedin.callback'));

    $response->assertOk();
    $response->assertViewHas('success', false);
    $response->assertViewHas('message', 'Error connecting account. Please try again.');
});
