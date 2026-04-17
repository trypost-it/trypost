<?php

declare(strict_types=1);

use App\Models\User;

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'Password123!',
    ]);

    $response->assertSessionHasNoErrors();
    $this->assertAuthenticated();
    $response->assertRedirect(route('register.success', absolute: false));
});

test('new users do not get a default workspace on registration', function () {
    $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'Password123!',
    ]);

    $user = User::where('email', 'test@example.com')->first();

    expect($user)->not->toBeNull();
    expect($user->account_id)->not->toBeNull();
    expect($user->workspaces()->count())->toBe(0);
    expect($user->current_workspace_id)->toBeNull();
});

test('new users can register with a timezone preference', function () {
    $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'Password123!',
        'timezone' => 'America/Sao_Paulo',
    ]);

    $user = User::where('email', 'test@example.com')->first();

    expect($user)->not->toBeNull();
    expect($user->workspaces()->count())->toBe(0);
});

test('new users do not have verified email by default', function () {
    $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'Password123!',
    ]);

    $user = User::where('email', 'test@example.com')->first();

    expect($user->email_verified_at)->toBeNull();
});

test('new users can register with deprecated timezone Asia/Calcutta', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'Password123!',
        'timezone' => 'Asia/Calcutta',
    ]);

    $response->assertSessionHasNoErrors();
    expect(User::where('email', 'test@example.com')->exists())->toBeTrue();
});

test('new users can register with deprecated timezone US/Eastern', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'Password123!',
        'timezone' => 'US/Eastern',
    ]);

    $response->assertSessionHasNoErrors();
    expect(User::where('email', 'test@example.com')->exists())->toBeTrue();
});

test('new users cannot register with invalid timezone', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'Password123!',
        'timezone' => 'Invalid/Timezone',
    ]);

    $response->assertSessionHasErrors('timezone');
    expect(User::where('email', 'test@example.com')->exists())->toBeFalse();
});

test('new users registering via invite have verified email automatically', function () {
    $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'Password123!',
        'redirect' => '/invites/some-invite-id',
    ]);

    $user = User::where('email', 'test@example.com')->first();

    expect($user->email_verified_at)->not->toBeNull();
});
