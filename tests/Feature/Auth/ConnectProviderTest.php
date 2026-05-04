<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User as SocialiteUser;

test('authenticated user can hit the connect-provider route for github', function () {
    $user = User::factory()->create();

    $driver = Mockery::mock(AbstractProvider::class);
    $driver->shouldReceive('scopes')->andReturnSelf();
    $driver->shouldReceive('redirectUrl')->andReturnSelf();
    $driver->shouldReceive('redirect')->andReturn(redirect('https://github.com/login/oauth/authorize'));
    Socialite::shouldReceive('driver')->with('github')->andReturn($driver);

    $this->actingAs($user)
        ->get(route('app.authentication.connect-provider', 'github'))
        ->assertRedirect('https://github.com/login/oauth/authorize');
});

test('authenticated user can hit the connect-provider route for google', function () {
    $user = User::factory()->create();

    $driver = Mockery::mock(AbstractProvider::class);
    $driver->shouldReceive('redirectUrl')->andReturnSelf();
    $driver->shouldReceive('redirect')->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));
    Socialite::shouldReceive('driver')->with('google-auth')->andReturn($driver);

    $this->actingAs($user)
        ->get(route('app.authentication.connect-provider', 'google'))
        ->assertRedirect('https://accounts.google.com/o/oauth2/auth');
});

test('connect-provider route rejects unknown provider', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('app.authentication.connect-provider', 'twitter'))
        ->assertNotFound();
});

test('connect-provider route requires authentication', function () {
    $this->get(route('app.authentication.connect-provider', 'github'))
        ->assertRedirect(route('login'));
});

test('connect-provider callback connects github to the current user', function () {
    $user = User::factory()->create([
        'email' => 'me@example.com',
        'google_id' => 'g-me',
        'github_id' => null,
    ]);

    $socialiteUser = new SocialiteUser;
    $socialiteUser->id = 'gh-me';
    $socialiteUser->name = 'Me';
    $socialiteUser->email = 'me@example.com';

    $driver = Mockery::mock(AbstractProvider::class);
    $driver->shouldReceive('scopes')->andReturnSelf();
    $driver->shouldReceive('redirectUrl')->andReturnSelf();
    $driver->shouldReceive('user')->andReturn($socialiteUser);
    Socialite::shouldReceive('driver')->with('github')->andReturn($driver);

    $this->actingAs($user)
        ->get(route('app.authentication.connect-provider.callback', 'github'))
        ->assertRedirect(route('app.authentication.edit'))
        ->assertSessionHas('flash.success');

    expect($user->fresh()->github_id)->toBe('gh-me');
});

test('connect-provider callback links github by current user, not by email', function () {
    $user = User::factory()->create([
        'email' => 'work@example.com',
        'google_id' => 'g-me',
        'github_id' => null,
    ]);

    $socialiteUser = new SocialiteUser;
    $socialiteUser->id = 'gh-personal';
    $socialiteUser->name = 'Me';
    $socialiteUser->email = 'personal@example.com';

    $driver = Mockery::mock(AbstractProvider::class);
    $driver->shouldReceive('scopes')->andReturnSelf();
    $driver->shouldReceive('redirectUrl')->andReturnSelf();
    $driver->shouldReceive('user')->andReturn($socialiteUser);
    Socialite::shouldReceive('driver')->with('github')->andReturn($driver);

    $this->actingAs($user)
        ->get(route('app.authentication.connect-provider.callback', 'github'))
        ->assertRedirect(route('app.authentication.edit'))
        ->assertSessionHas('flash.success');

    expect($user->fresh()->github_id)->toBe('gh-personal');
    expect(User::where('email', 'personal@example.com')->exists())->toBeFalse();
    $this->assertAuthenticatedAs($user);
});

test('connect-provider callback rejects when github account is already linked to another user', function () {
    User::factory()->create(['github_id' => 'gh-taken']);

    $me = User::factory()->create(['email' => 'me@example.com', 'github_id' => null]);

    $socialiteUser = new SocialiteUser;
    $socialiteUser->id = 'gh-taken';
    $socialiteUser->name = 'Me';
    $socialiteUser->email = 'me@example.com';

    $driver = Mockery::mock(AbstractProvider::class);
    $driver->shouldReceive('scopes')->andReturnSelf();
    $driver->shouldReceive('redirectUrl')->andReturnSelf();
    $driver->shouldReceive('user')->andReturn($socialiteUser);
    Socialite::shouldReceive('driver')->with('github')->andReturn($driver);

    $this->actingAs($me)
        ->get(route('app.authentication.connect-provider.callback', 'github'))
        ->assertRedirect(route('app.authentication.edit'))
        ->assertSessionHas('flash.error');

    expect($me->fresh()->github_id)->toBeNull();
    $this->assertAuthenticatedAs($me);
});

test('connect-provider callback connects google to the current user', function () {
    $user = User::factory()->create([
        'email' => 'me@example.com',
        'github_id' => 'gh-me',
        'google_id' => null,
    ]);

    $socialiteUser = new SocialiteUser;
    $socialiteUser->id = 'g-me';
    $socialiteUser->name = 'Me';
    $socialiteUser->email = 'me@example.com';

    $driver = Mockery::mock(AbstractProvider::class);
    $driver->shouldReceive('redirectUrl')->andReturnSelf();
    $driver->shouldReceive('user')->andReturn($socialiteUser);
    Socialite::shouldReceive('driver')->with('google-auth')->andReturn($driver);

    $this->actingAs($user)
        ->get(route('app.authentication.connect-provider.callback', 'google'))
        ->assertRedirect(route('app.authentication.edit'))
        ->assertSessionHas('flash.success');

    expect($user->fresh()->google_id)->toBe('g-me');
});

test('connect-provider callback rejects when google account is already linked to another user', function () {
    User::factory()->create(['google_id' => 'g-taken']);

    $me = User::factory()->create(['email' => 'me@example.com', 'google_id' => null]);

    $socialiteUser = new SocialiteUser;
    $socialiteUser->id = 'g-taken';
    $socialiteUser->name = 'Me';
    $socialiteUser->email = 'me@example.com';

    $driver = Mockery::mock(AbstractProvider::class);
    $driver->shouldReceive('redirectUrl')->andReturnSelf();
    $driver->shouldReceive('user')->andReturn($socialiteUser);
    Socialite::shouldReceive('driver')->with('google-auth')->andReturn($driver);

    $this->actingAs($me)
        ->get(route('app.authentication.connect-provider.callback', 'google'))
        ->assertRedirect(route('app.authentication.edit'))
        ->assertSessionHas('flash.error');

    expect($me->fresh()->google_id)->toBeNull();
    $this->assertAuthenticatedAs($me);
});

test('connect-provider callback rejects unknown provider', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('app.authentication.connect-provider.callback', 'twitter'))
        ->assertNotFound();
});

test('connect-provider callback requires authentication', function () {
    $this->get(route('app.authentication.connect-provider.callback', 'github'))
        ->assertRedirect(route('login'));
});
