<?php

declare(strict_types=1);

use App\Enums\SocialAccount\Platform;
use App\Enums\SocialAccount\Status;
use App\Enums\UserWorkspace\Role;
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
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Owner->value]);
});

test('facebook connect redirects to oauth provider', function () {
    $driverMock = Mockery::mock();
    $driverMock->shouldReceive('usingGraphVersion')->andReturnSelf();
    $driverMock->shouldReceive('setScopes')->andReturnSelf();
    $driverMock->shouldReceive('redirect')->andReturn(Mockery::mock([
        'getTargetUrl' => 'https://www.facebook.com/v25.0/dialog/oauth?test=1',
    ]));

    Socialite::shouldReceive('driver')
        ->with('facebook')
        ->andReturn($driverMock);

    $response = $this->actingAs($this->user)
        ->withHeader('X-Inertia', 'true')
        ->get(route('app.social.facebook.connect'));

    $response->assertStatus(409); // Inertia::location returns 409 with X-Inertia header

    expect(session('social_connect_workspace'))->toBe($this->workspace->id);
});

test('facebook oauth callback creates account with single page', function () {
    session([
        'social_connect_workspace' => $this->workspace->id,
    ]);

    $socialiteUser = Mockery::mock(SocialiteUser::class);
    $socialiteUser->shouldReceive('getId')->andReturn('facebook_user_123');
    $socialiteUser->token = 'test-user-token';

    Socialite::shouldReceive('driver')
        ->with('facebook')
        ->andReturn(Mockery::mock()->shouldReceive('usingGraphVersion')->andReturnSelf()->shouldReceive('user')->andReturn($socialiteUser)->getMock());

    Http::fake([
        'https://graph.facebook.com/*/me/accounts*' => Http::response([
            'data' => [
                [
                    'id' => 'page_123',
                    'name' => 'My Facebook Page',
                    'username' => 'myfbpage',
                    'picture' => ['data' => ['url' => null]],
                    'access_token' => 'page-access-token',
                ],
            ],
        ], 200),
    ]);

    $response = $this->actingAs($this->user)->get(route('app.social.facebook.callback'));

    $response->assertOk();
    $response->assertViewIs('auth.social-callback');
    $response->assertViewHas('success', true);

    $this->assertDatabaseHas('social_accounts', [
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::Facebook->value,
        'platform_user_id' => 'page_123',
        'username' => 'myfbpage',
        'display_name' => 'My Facebook Page',
        'status' => Status::Connected->value,
    ]);
});

test('facebook callback redirects to page selection when multiple pages', function () {
    session([
        'social_connect_workspace' => $this->workspace->id,
    ]);

    $socialiteUser = Mockery::mock(SocialiteUser::class);
    $socialiteUser->shouldReceive('getId')->andReturn('facebook_user_123');
    $socialiteUser->token = 'test-user-token';

    Socialite::shouldReceive('driver')
        ->with('facebook')
        ->andReturn(Mockery::mock()->shouldReceive('usingGraphVersion')->andReturnSelf()->shouldReceive('user')->andReturn($socialiteUser)->getMock());

    Http::fake([
        'https://graph.facebook.com/*/me/accounts*' => Http::response([
            'data' => [
                [
                    'id' => 'page_1',
                    'name' => 'Page 1',
                    'username' => 'page1',
                    'picture' => ['data' => ['url' => null]],
                    'access_token' => 'token-1',
                ],
                [
                    'id' => 'page_2',
                    'name' => 'Page 2',
                    'username' => 'page2',
                    'picture' => ['data' => ['url' => null]],
                    'access_token' => 'token-2',
                ],
            ],
        ], 200),
    ]);

    $response = $this->actingAs($this->user)->get(route('app.social.facebook.callback'));

    $response->assertRedirect(route('app.social.facebook.select-page'));
    expect(session('facebook_oauth'))->not->toBeNull();
});

test('facebook callback fails when no pages found', function () {
    session([
        'social_connect_workspace' => $this->workspace->id,
    ]);

    $socialiteUser = Mockery::mock(SocialiteUser::class);
    $socialiteUser->shouldReceive('getId')->andReturn('facebook_user_123');
    $socialiteUser->token = 'test-user-token';

    Socialite::shouldReceive('driver')
        ->with('facebook')
        ->andReturn(Mockery::mock()->shouldReceive('usingGraphVersion')->andReturnSelf()->shouldReceive('user')->andReturn($socialiteUser)->getMock());

    Http::fake([
        'https://graph.facebook.com/*/me/accounts*' => Http::response([
            'data' => [],
        ], 200),
    ]);

    $response = $this->actingAs($this->user)->get(route('app.social.facebook.callback'));

    $response->assertOk();
    $response->assertViewHas('success', false);
    $response->assertViewHas('message', 'No Facebook Pages found. You need to be an admin of at least one page.');
});

test('facebook callback fails with expired session', function () {
    // No session data - simulating expired session

    $response = $this->actingAs($this->user)->get(route('app.social.facebook.callback'));

    $response->assertOk();
    $response->assertViewHas('success', false);
    $response->assertViewHas('message', 'Session expired. Please try again.');
});

test('user can connect multiple facebook accounts', function () {
    SocialAccount::factory()->facebook()->create([
        'workspace_id' => $this->workspace->id,
        'platform_user_id' => 'page_existing',
    ]);

    session([
        'social_connect_workspace' => $this->workspace->id,
    ]);

    $socialiteUser = Mockery::mock(SocialiteUser::class);
    $socialiteUser->shouldReceive('getId')->andReturn('facebook_user_456');
    $socialiteUser->token = 'new-user-token';

    Socialite::shouldReceive('driver')
        ->with('facebook')
        ->andReturn(Mockery::mock()->shouldReceive('usingGraphVersion')->andReturnSelf()->shouldReceive('user')->andReturn($socialiteUser)->getMock());

    Http::fake([
        'https://graph.facebook.com/*/me/accounts*' => Http::response([
            'data' => [
                [
                    'id' => 'page_new',
                    'name' => 'New Page',
                    'picture' => ['data' => ['url' => null]],
                    'access_token' => 'page-token',
                ],
            ],
        ], 200),
    ]);

    $response = $this->actingAs($this->user)->get(route('app.social.facebook.callback'));

    $response->assertOk();
    $response->assertViewHas('success', true);

    expect($this->workspace->socialAccounts()->where('platform', Platform::Facebook)->count())->toBe(2);
});

test('facebook callback handles oauth errors gracefully', function () {
    session([
        'social_connect_workspace' => $this->workspace->id,
    ]);

    $mock = Mockery::mock();
    $mock->shouldReceive('user')->andThrow(new Exception('OAuth error'));

    Socialite::shouldReceive('driver')
        ->with('facebook')
        ->andReturn($mock);

    $response = $this->actingAs($this->user)->get(route('app.social.facebook.callback'));

    $response->assertOk();
    $response->assertViewHas('success', false);
    $response->assertViewHas('message', 'Error connecting account. Please try again.');
});

test('facebook page selection creates account', function () {
    session([
        'social_connect_workspace' => $this->workspace->id,
        'facebook_oauth' => [
            'user_token' => 'test-user-token',
            'user_id' => 'facebook_user_123',
            'pages' => [
                [
                    'id' => 'page_123',
                    'name' => 'My Facebook Page',
                    'username' => 'myfbpage',
                    'picture' => null,
                    'access_token' => 'page-access-token',
                ],
                [
                    'id' => 'page_456',
                    'name' => 'Other Page',
                    'username' => 'otherpage',
                    'picture' => null,
                    'access_token' => 'other-page-token',
                ],
            ],
        ],
    ]);

    $response = $this->actingAs($this->user)->post(route('app.social.facebook.select'), [
        'page_id' => 'page_123',
    ]);

    $response->assertOk();
    $response->assertViewHas('success', true);

    $this->assertDatabaseHas('social_accounts', [
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::Facebook->value,
        'platform_user_id' => 'page_123',
        'username' => 'myfbpage',
    ]);
});

test('facebook page selection fails with expired session', function () {
    // No session data

    $response = $this->actingAs($this->user)->post(route('app.social.facebook.select'), [
        'page_id' => 'page_123',
    ]);

    $response->assertOk();
    $response->assertViewHas('success', false);
    $response->assertViewHas('message', 'Session expired. Please try again.');
});

test('facebook page selection fails with invalid page id', function () {
    session([
        'social_connect_workspace' => $this->workspace->id,
        'facebook_oauth' => [
            'user_token' => 'test-user-token',
            'user_id' => 'facebook_user_123',
            'pages' => [
                [
                    'id' => 'page_123',
                    'name' => 'My Facebook Page',
                    'picture' => null,
                    'access_token' => 'page-access-token',
                ],
            ],
        ],
    ]);

    $response = $this->actingAs($this->user)->post(route('app.social.facebook.select'), [
        'page_id' => 'invalid_page_id',
    ]);

    $response->assertOk();
    $response->assertViewHas('success', false);
    $response->assertViewHas('message', 'Page not found.');
});
