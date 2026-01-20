<?php

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
    $response->assertRedirect(route('onboarding.step1', absolute: false));
});

test('new users get a default workspace on registration', function () {
    $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'Password123!',
    ]);

    $user = User::where('email', 'test@example.com')->first();

    expect($user)->not->toBeNull();
    expect($user->workspaces)->toHaveCount(1);
    expect($user->workspaces->first()->name)->toBe("Test User's Workspace");
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
