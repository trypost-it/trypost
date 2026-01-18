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

test('linkedin page connect redirects to oauth provider', function () {
    Socialite::shouldReceive('driver')
        ->with('linkedin-openid')
        ->andReturn(Mockery::mock([
            'scopes' => Mockery::self(),
            'with' => Mockery::self(),
            'redirect' => Mockery::mock([
                'getTargetUrl' => 'https://www.linkedin.com/oauth/v2/authorization?test=1',
            ]),
        ]));

    $response = $this->actingAs($this->user)
        ->withHeader('X-Inertia', 'true')
        ->get(route('social.linkedin-page.connect'));

    $response->assertStatus(409); // Inertia::location returns 409 with X-Inertia header

    expect(session('social_connect_workspace'))->toBe($this->workspace->id);
});

test('linkedin page oauth callback fetches organizations', function () {
    session([
        'social_connect_workspace' => $this->workspace->id,
    ]);

    $socialiteUser = Mockery::mock(SocialiteUser::class);
    $socialiteUser->shouldReceive('getId')->andReturn('user123');
    $socialiteUser->shouldReceive('getName')->andReturn('John Doe');
    $socialiteUser->shouldReceive('getAvatar')->andReturn(null);
    $socialiteUser->token = 'test-access-token';
    $socialiteUser->refreshToken = 'test-refresh-token';
    $socialiteUser->expiresIn = 5184000;

    $socialiteMock = Mockery::mock();
    $socialiteMock->shouldReceive('scopes')->andReturn($socialiteMock);
    $socialiteMock->shouldReceive('with')->andReturn($socialiteMock);
    $socialiteMock->shouldReceive('user')->andReturn($socialiteUser);

    Socialite::shouldReceive('driver')
        ->with('linkedin-openid')
        ->andReturn($socialiteMock);

    Http::fake([
        'https://api.linkedin.com/v2/organizationAcls*' => Http::response([
            'elements' => [
                [
                    'organization~' => [
                        'id' => 123456,
                        'localizedName' => 'Test Company',
                        'vanityName' => 'testcompany',
                    ],
                ],
            ],
        ], 200),
    ]);

    $response = $this->actingAs($this->user)->get(route('social.linkedin-page.callback'));

    $response->assertRedirect(route('social.linkedin-page.select-page'));

    expect(session('linkedin_page_pending'))->not->toBeNull();
    expect(session('linkedin_page_pending.organizations'))->toHaveCount(1);
});

test('linkedin page callback fails when user has no organizations', function () {
    session([
        'social_connect_workspace' => $this->workspace->id,
    ]);

    $socialiteUser = Mockery::mock(SocialiteUser::class);
    $socialiteUser->shouldReceive('getId')->andReturn('user123');
    $socialiteUser->shouldReceive('getName')->andReturn('John Doe');
    $socialiteUser->shouldReceive('getAvatar')->andReturn(null);
    $socialiteUser->token = 'test-access-token';
    $socialiteUser->refreshToken = 'test-refresh-token';
    $socialiteUser->expiresIn = 5184000;

    $socialiteMock = Mockery::mock();
    $socialiteMock->shouldReceive('scopes')->andReturn($socialiteMock);
    $socialiteMock->shouldReceive('with')->andReturn($socialiteMock);
    $socialiteMock->shouldReceive('user')->andReturn($socialiteUser);

    Socialite::shouldReceive('driver')
        ->with('linkedin-openid')
        ->andReturn($socialiteMock);

    Http::fake([
        'https://api.linkedin.com/v2/organizationAcls*' => Http::response([
            'elements' => [],
        ], 200),
    ]);

    $response = $this->actingAs($this->user)->get(route('social.linkedin-page.callback'));

    $response->assertOk();
    $response->assertViewHas('success', false);
    $response->assertViewHas('message', 'You are not an administrator of any LinkedIn page.');
});

test('linkedin page callback fails with expired session', function () {
    // No session data - simulating expired session

    $response = $this->actingAs($this->user)->get(route('social.linkedin-page.callback'));

    $response->assertOk();
    $response->assertViewHas('success', false);
    $response->assertViewHas('message', 'Session expired. Please try again.');
});

test('linkedin page select creates account', function () {
    session([
        'linkedin_page_pending' => [
            'workspace_id' => $this->workspace->id,
            'user_id' => 'user123',
            'name' => 'John Doe',
            'avatar' => null,
            'token' => 'test-access-token',
            'refresh_token' => 'test-refresh-token',
            'expires_in' => 5184000,
            'organizations' => [
                ['id' => 123456, 'name' => 'Test Company', 'vanity_name' => 'testcompany', 'logo' => null],
            ],
        ],
    ]);

    $response = $this->actingAs($this->user)->post(route('social.linkedin-page.select'), [
        'organization_id' => 123456,
        'organization_name' => 'Test Company',
        'organization_vanity_name' => 'testcompany',
        'organization_logo' => null,
    ]);

    $response->assertOk();
    $response->assertViewIs('auth.social-callback');
    $response->assertViewHas('success', true);

    $this->assertDatabaseHas('social_accounts', [
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::LinkedInPage->value,
        'platform_user_id' => 123456,
        'username' => 'testcompany',
        'display_name' => 'Test Company',
        'status' => Status::Connected->value,
    ]);
});

test('linkedin page select fails with expired session', function () {
    // No session data

    $response = $this->actingAs($this->user)->post(route('social.linkedin-page.select'), [
        'organization_id' => 123456,
        'organization_name' => 'Test Company',
        'organization_vanity_name' => 'testcompany',
    ]);

    $response->assertOk();
    $response->assertViewHas('success', false);
    $response->assertViewHas('message', 'Session expired. Please try again.');
});

test('user cannot connect linkedin page if already connected via connect route', function () {
    SocialAccount::factory()->linkedinPage()->create([
        'workspace_id' => $this->workspace->id,
        'platform_user_id' => '123456',
    ]);

    // The "already connected" check happens in the connect method, not callback
    $response = $this->actingAs($this->user)->get(route('social.linkedin-page.connect'));

    $response->assertRedirect();
    $response->assertSessionHas('error', 'This platform is already connected.');
});

test('user can reconnect disconnected linkedin page account', function () {
    $existingAccount = SocialAccount::factory()->linkedinPage()->disconnected()->create([
        'workspace_id' => $this->workspace->id,
        'platform_user_id' => '123456',
    ]);

    session([
        'linkedin_page_pending' => [
            'workspace_id' => $this->workspace->id,
            'user_id' => 'user123',
            'name' => 'John Doe',
            'avatar' => null,
            'token' => 'new-access-token',
            'refresh_token' => 'new-refresh-token',
            'expires_in' => 5184000,
            'organizations' => [
                ['id' => 123456, 'name' => 'Test Company', 'vanity_name' => 'testcompany', 'logo' => null],
            ],
            'reconnect_id' => $existingAccount->id,
        ],
    ]);

    $response = $this->actingAs($this->user)->post(route('social.linkedin-page.select'), [
        'organization_id' => 123456,
        'organization_name' => 'Test Company',
        'organization_vanity_name' => 'testcompany',
        'organization_logo' => null,
    ]);

    $response->assertOk();
    $response->assertViewHas('success', true);

    $existingAccount->refresh();
    expect($existingAccount->status)->toBe(Status::Connected);
    expect($existingAccount->access_token)->toBe('new-access-token');
});

test('linkedin page callback handles oauth errors gracefully', function () {
    session([
        'social_connect_workspace' => $this->workspace->id,
    ]);

    $socialiteMock = Mockery::mock();
    $socialiteMock->shouldReceive('scopes')->andReturn($socialiteMock);
    $socialiteMock->shouldReceive('with')->andReturn($socialiteMock);
    $socialiteMock->shouldReceive('user')->andThrow(new \Exception('OAuth error'));

    Socialite::shouldReceive('driver')
        ->with('linkedin-openid')
        ->andReturn($socialiteMock);

    $response = $this->actingAs($this->user)->get(route('social.linkedin-page.callback'));

    $response->assertOk();
    $response->assertViewHas('success', false);
    $response->assertViewHas('message', 'Error connecting account. Please try again.');
});
