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

test('tiktok connect redirects to oauth provider', function () {
    Socialite::shouldReceive('driver')
        ->with('tiktok')
        ->andReturn(Mockery::mock([
            'scopes' => Mockery::self(),
            'redirect' => Mockery::mock([
                'getTargetUrl' => 'https://www.tiktok.com/v2/auth/authorize?test=1',
            ]),
        ]));

    $response = $this->actingAs($this->user)
        ->withHeader('X-Inertia', 'true')
        ->get(route('social.tiktok.connect'));

    $response->assertStatus(409); // Inertia::location returns 409 with X-Inertia header

    expect(session('social_connect_workspace'))->toBe($this->workspace->id);
});

test('tiktok oauth callback creates account', function () {
    session([
        'social_connect_workspace' => $this->workspace->id,
    ]);

    $socialiteUser = Mockery::mock(SocialiteUser::class);
    $socialiteUser->shouldReceive('getId')->andReturn('tiktok123');
    $socialiteUser->shouldReceive('getNickname')->andReturn('tiktoker');
    $socialiteUser->shouldReceive('getName')->andReturn('TikTok User');
    $socialiteUser->shouldReceive('getAvatar')->andReturn(null);
    $socialiteUser->token = 'test-access-token';
    $socialiteUser->refreshToken = 'test-refresh-token';
    $socialiteUser->expiresIn = 86400;
    $socialiteUser->approvedScopes = ['user.info.basic', 'user.info.profile', 'video.publish'];

    $socialiteMock = Mockery::mock();
    $socialiteMock->shouldReceive('scopes')->andReturn($socialiteMock);
    $socialiteMock->shouldReceive('user')->andReturn($socialiteUser);

    Socialite::shouldReceive('driver')
        ->with('tiktok')
        ->andReturn($socialiteMock);

    $response = $this->actingAs($this->user)->get(route('social.tiktok.callback'));

    $response->assertOk();
    $response->assertViewIs('auth.social-callback');
    $response->assertViewHas('success', true);

    $this->assertDatabaseHas('social_accounts', [
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::TikTok->value,
        'platform_user_id' => 'tiktok123',
        'username' => 'tiktoker',
        'status' => Status::Connected->value,
    ]);
});

test('tiktok callback fails with expired session', function () {
    // No session data - simulating expired session

    $response = $this->actingAs($this->user)->get(route('social.tiktok.callback'));

    $response->assertOk();
    $response->assertViewHas('success', false);
    $response->assertViewHas('message', 'Session expired. Please try again.');
});

test('user cannot connect tiktok if already connected', function () {
    SocialAccount::factory()->tiktok()->create([
        'workspace_id' => $this->workspace->id,
        'platform_user_id' => 'tiktok123',
    ]);

    session([
        'social_connect_workspace' => $this->workspace->id,
    ]);

    $socialiteUser = Mockery::mock(SocialiteUser::class);
    $socialiteUser->shouldReceive('getId')->andReturn('tiktok456');
    $socialiteUser->shouldReceive('getNickname')->andReturn('anothertiktoker');
    $socialiteUser->shouldReceive('getName')->andReturn('Another TikTok User');
    $socialiteUser->shouldReceive('getAvatar')->andReturn(null);
    $socialiteUser->token = 'new-access-token';
    $socialiteUser->refreshToken = 'new-refresh-token';
    $socialiteUser->expiresIn = 86400;

    $socialiteMock = Mockery::mock();
    $socialiteMock->shouldReceive('scopes')->andReturn($socialiteMock);
    $socialiteMock->shouldReceive('user')->andReturn($socialiteUser);

    Socialite::shouldReceive('driver')
        ->with('tiktok')
        ->andReturn($socialiteMock);

    $response = $this->actingAs($this->user)->get(route('social.tiktok.callback'));

    $response->assertOk();
    $response->assertViewHas('success', false);
    $response->assertViewHas('message', 'This platform is already connected.');
});

test('user can reconnect disconnected tiktok account', function () {
    $existingAccount = SocialAccount::factory()->tiktok()->disconnected()->create([
        'workspace_id' => $this->workspace->id,
        'platform_user_id' => 'tiktok123',
    ]);

    session([
        'social_connect_workspace' => $this->workspace->id,
        'social_reconnect_id' => $existingAccount->id,
    ]);

    $socialiteUser = Mockery::mock(SocialiteUser::class);
    $socialiteUser->shouldReceive('getId')->andReturn('tiktok123');
    $socialiteUser->shouldReceive('getNickname')->andReturn('tiktoker');
    $socialiteUser->shouldReceive('getName')->andReturn('TikTok User');
    $socialiteUser->shouldReceive('getAvatar')->andReturn(null);
    $socialiteUser->token = 'new-access-token';
    $socialiteUser->refreshToken = 'new-refresh-token';
    $socialiteUser->expiresIn = 86400;
    $socialiteUser->approvedScopes = ['user.info.basic', 'user.info.profile', 'video.publish'];

    $socialiteMock = Mockery::mock();
    $socialiteMock->shouldReceive('scopes')->andReturn($socialiteMock);
    $socialiteMock->shouldReceive('user')->andReturn($socialiteUser);

    Socialite::shouldReceive('driver')
        ->with('tiktok')
        ->andReturn($socialiteMock);

    $response = $this->actingAs($this->user)->get(route('social.tiktok.callback'));

    $response->assertOk();
    $response->assertViewHas('success', true);

    $existingAccount->refresh();
    expect($existingAccount->status)->toBe(Status::Connected);
    expect($existingAccount->access_token)->toBe('new-access-token');
});

test('tiktok callback handles oauth errors gracefully', function () {
    session([
        'social_connect_workspace' => $this->workspace->id,
    ]);

    $mock = Mockery::mock();
    $mock->shouldReceive('scopes')->andReturn($mock);
    $mock->shouldReceive('user')->andThrow(new \Exception('OAuth error'));

    Socialite::shouldReceive('driver')
        ->with('tiktok')
        ->andReturn($mock);

    $response = $this->actingAs($this->user)->get(route('social.tiktok.callback'));

    $response->assertOk();
    $response->assertViewHas('success', false);
    $response->assertViewHas('message', 'Error connecting account. Please try again.');
});
