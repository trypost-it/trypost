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

test('youtube connect redirects to oauth provider', function () {
    Socialite::shouldReceive('driver')
        ->with('google')
        ->andReturn(Mockery::mock([
            'scopes' => Mockery::self(),
            'with' => Mockery::self(),
            'redirect' => Mockery::mock([
                'getTargetUrl' => 'https://accounts.google.com/o/oauth2/v2/auth?test=1',
            ]),
        ]));

    $response = $this->actingAs($this->user)
        ->withHeader('X-Inertia', 'true')
        ->get(route('social.youtube.connect'));

    $response->assertStatus(409); // Inertia::location returns 409 with X-Inertia header

    expect(session('social_connect_workspace'))->toBe($this->workspace->id);
});

test('youtube oauth callback creates account with single channel', function () {
    session([
        'social_connect_workspace' => $this->workspace->id,
    ]);

    $socialiteUser = Mockery::mock(SocialiteUser::class);
    $socialiteUser->shouldReceive('getId')->andReturn('google_user_123');
    $socialiteUser->token = 'test-access-token';
    $socialiteUser->refreshToken = 'test-refresh-token';
    $socialiteUser->expiresIn = 3600;

    Socialite::shouldReceive('driver')
        ->with('google')
        ->andReturn(Mockery::mock([
            'user' => $socialiteUser,
        ]));

    Http::fake([
        'https://www.googleapis.com/youtube/v3/channels*' => Http::response([
            'items' => [
                [
                    'id' => 'UC_channel_123',
                    'snippet' => [
                        'title' => 'My YouTube Channel',
                        'description' => 'Channel description',
                        'customUrl' => '@mychannel',
                        'thumbnails' => [
                            'default' => ['url' => null],
                        ],
                    ],
                    'statistics' => [
                        'subscriberCount' => 1000,
                    ],
                ],
            ],
        ], 200),
    ]);

    $response = $this->actingAs($this->user)->get(route('social.youtube.callback'));

    $response->assertOk();
    $response->assertViewIs('auth.social-callback');
    $response->assertViewHas('success', true);

    $this->assertDatabaseHas('social_accounts', [
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::YouTube->value,
        'platform_user_id' => 'UC_channel_123',
        'username' => 'mychannel',
        'display_name' => 'My YouTube Channel',
        'status' => Status::Connected->value,
    ]);
});

test('youtube callback redirects to channel selection when multiple channels', function () {
    session([
        'social_connect_workspace' => $this->workspace->id,
    ]);

    $socialiteUser = Mockery::mock(SocialiteUser::class);
    $socialiteUser->shouldReceive('getId')->andReturn('google_user_123');
    $socialiteUser->token = 'test-access-token';
    $socialiteUser->refreshToken = 'test-refresh-token';
    $socialiteUser->expiresIn = 3600;

    Socialite::shouldReceive('driver')
        ->with('google')
        ->andReturn(Mockery::mock([
            'user' => $socialiteUser,
        ]));

    Http::fake([
        'https://www.googleapis.com/youtube/v3/channels*' => Http::response([
            'items' => [
                [
                    'id' => 'UC_channel_1',
                    'snippet' => [
                        'title' => 'Channel 1',
                        'customUrl' => '@channel1',
                        'thumbnails' => ['default' => ['url' => null]],
                    ],
                    'statistics' => ['subscriberCount' => 500],
                ],
                [
                    'id' => 'UC_channel_2',
                    'snippet' => [
                        'title' => 'Channel 2',
                        'customUrl' => '@channel2',
                        'thumbnails' => ['default' => ['url' => null]],
                    ],
                    'statistics' => ['subscriberCount' => 1000],
                ],
            ],
        ], 200),
    ]);

    $response = $this->actingAs($this->user)->get(route('social.youtube.callback'));

    $response->assertRedirect(route('social.youtube.select-channel'));
    expect(session('youtube_oauth'))->not->toBeNull();
});

test('youtube callback fails when no channels found', function () {
    session([
        'social_connect_workspace' => $this->workspace->id,
    ]);

    $socialiteUser = Mockery::mock(SocialiteUser::class);
    $socialiteUser->shouldReceive('getId')->andReturn('google_user_123');
    $socialiteUser->token = 'test-access-token';
    $socialiteUser->refreshToken = 'test-refresh-token';
    $socialiteUser->expiresIn = 3600;

    Socialite::shouldReceive('driver')
        ->with('google')
        ->andReturn(Mockery::mock([
            'user' => $socialiteUser,
        ]));

    Http::fake([
        'https://www.googleapis.com/youtube/v3/channels*' => Http::response([
            'items' => [],
        ], 200),
    ]);

    $response = $this->actingAs($this->user)->get(route('social.youtube.callback'));

    $response->assertOk();
    $response->assertViewHas('success', false);
    $response->assertViewHas('message', 'No YouTube channels found. Please create a channel first.');
});

test('youtube callback fails with expired session', function () {
    // No session data - simulating expired session

    $response = $this->actingAs($this->user)->get(route('social.youtube.callback'));

    $response->assertOk();
    $response->assertViewHas('success', false);
    $response->assertViewHas('message', 'Session expired. Please try again.');
});

test('user cannot connect youtube if already connected', function () {
    SocialAccount::factory()->youtube()->create([
        'workspace_id' => $this->workspace->id,
        'platform_user_id' => 'UC_channel_123',
    ]);

    session([
        'social_connect_workspace' => $this->workspace->id,
    ]);

    $socialiteUser = Mockery::mock(SocialiteUser::class);
    $socialiteUser->shouldReceive('getId')->andReturn('google_user_456');
    $socialiteUser->token = 'new-access-token';
    $socialiteUser->refreshToken = 'new-refresh-token';
    $socialiteUser->expiresIn = 3600;

    Socialite::shouldReceive('driver')
        ->with('google')
        ->andReturn(Mockery::mock([
            'user' => $socialiteUser,
        ]));

    Http::fake([
        'https://www.googleapis.com/youtube/v3/channels*' => Http::response([
            'items' => [
                [
                    'id' => 'UC_another_channel',
                    'snippet' => [
                        'title' => 'Another Channel',
                        'customUrl' => '@anotherchannel',
                        'thumbnails' => ['default' => ['url' => null]],
                    ],
                    'statistics' => ['subscriberCount' => 500],
                ],
            ],
        ], 200),
    ]);

    $response = $this->actingAs($this->user)->get(route('social.youtube.callback'));

    $response->assertOk();
    $response->assertViewHas('success', false);
    $response->assertViewHas('message', 'This platform is already connected.');
});

test('user can reconnect disconnected youtube account', function () {
    $existingAccount = SocialAccount::factory()->youtube()->disconnected()->create([
        'workspace_id' => $this->workspace->id,
        'platform_user_id' => 'UC_channel_123',
        'meta' => ['channel_id' => 'UC_channel_123'],
    ]);

    session([
        'social_connect_workspace' => $this->workspace->id,
        'social_reconnect_id' => $existingAccount->id,
    ]);

    $socialiteUser = Mockery::mock(SocialiteUser::class);
    $socialiteUser->shouldReceive('getId')->andReturn('google_user_123');
    $socialiteUser->token = 'new-access-token';
    $socialiteUser->refreshToken = 'new-refresh-token';
    $socialiteUser->expiresIn = 3600;

    Socialite::shouldReceive('driver')
        ->with('google')
        ->andReturn(Mockery::mock([
            'user' => $socialiteUser,
        ]));

    Http::fake([
        'https://www.googleapis.com/youtube/v3/channels*' => Http::response([
            'items' => [
                [
                    'id' => 'UC_channel_123',
                    'snippet' => [
                        'title' => 'My YouTube Channel',
                        'customUrl' => '@mychannel',
                        'thumbnails' => ['default' => ['url' => null]],
                    ],
                    'statistics' => ['subscriberCount' => 1000],
                ],
            ],
        ], 200),
    ]);

    $response = $this->actingAs($this->user)->get(route('social.youtube.callback'));

    $response->assertOk();
    $response->assertViewHas('success', true);

    $existingAccount->refresh();
    expect($existingAccount->status)->toBe(Status::Connected);
    expect($existingAccount->access_token)->toBe('new-access-token');
});

test('youtube callback handles oauth errors gracefully', function () {
    session([
        'social_connect_workspace' => $this->workspace->id,
    ]);

    $mock = Mockery::mock();
    $mock->shouldReceive('user')->andThrow(new \Exception('OAuth error'));

    Socialite::shouldReceive('driver')
        ->with('google')
        ->andReturn($mock);

    $response = $this->actingAs($this->user)->get(route('social.youtube.callback'));

    $response->assertOk();
    $response->assertViewHas('success', false);
    $response->assertViewHas('message', 'Error connecting account. Please try again.');
});

test('youtube channel selection creates account', function () {
    session([
        'social_connect_workspace' => $this->workspace->id,
        'youtube_oauth' => [
            'access_token' => 'test-access-token',
            'refresh_token' => 'test-refresh-token',
            'expires_in' => 3600,
            'user_id' => 'google_user_123',
        ],
    ]);

    Http::fake([
        'https://www.googleapis.com/youtube/v3/channels*' => Http::response([
            'items' => [
                [
                    'id' => 'UC_channel_123',
                    'snippet' => [
                        'title' => 'My YouTube Channel',
                        'description' => 'Channel description',
                        'customUrl' => '@mychannel',
                        'thumbnails' => ['default' => ['url' => null]],
                    ],
                    'statistics' => ['subscriberCount' => 1000],
                ],
            ],
        ], 200),
    ]);

    $response = $this->actingAs($this->user)->post(route('social.youtube.select'), [
        'channel_id' => 'UC_channel_123',
    ]);

    $response->assertOk();
    $response->assertViewHas('success', true);

    $this->assertDatabaseHas('social_accounts', [
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::YouTube->value,
        'platform_user_id' => 'UC_channel_123',
        'username' => 'mychannel',
    ]);
});

test('youtube channel selection fails with expired session', function () {
    // No session data

    $response = $this->actingAs($this->user)->post(route('social.youtube.select'), [
        'channel_id' => 'UC_channel_123',
    ]);

    $response->assertOk();
    $response->assertViewHas('success', false);
    $response->assertViewHas('message', 'Session expired. Please try again.');
});
