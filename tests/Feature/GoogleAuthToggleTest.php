<?php

declare(strict_types=1);

test('login page loads when google auth is disabled', function () {
    config(['trypost.google_auth_enabled' => false]);

    $response = $this->get(route('login'));

    $response->assertOk();
});

test('login page loads when google auth is enabled', function () {
    config(['trypost.google_auth_enabled' => true]);

    $response = $this->get(route('login'));

    $response->assertOk();
});

test('register page loads when google auth is disabled', function () {
    config(['trypost.google_auth_enabled' => false]);

    $response = $this->get(route('register'));

    $response->assertOk();
});

test('register page loads when google auth is enabled', function () {
    config(['trypost.google_auth_enabled' => true]);

    $response = $this->get(route('register'));

    $response->assertOk();
});

test('login page shares google auth enabled prop as false when disabled', function () {
    config(['trypost.google_auth_enabled' => false]);

    $response = $this->get(route('login'));

    $response->assertOk();

    $page = $response->original->getData()['page'];
    expect($page['props']['googleAuthEnabled'])->toBeFalse();
});

test('login page shares google auth enabled prop as true when enabled', function () {
    config(['trypost.google_auth_enabled' => true]);

    $response = $this->get(route('login'));

    $response->assertOk();

    $page = $response->original->getData()['page'];
    expect($page['props']['googleAuthEnabled'])->toBeTrue();
});

test('google auth redirect route exists', function () {
    $response = $this->get(route('auth.google.redirect'));

    // Should redirect to Google OAuth, not 404
    $response->assertRedirect();
});

test('google auth callback route exists', function () {
    $response = $this->get(route('auth.google.callback'));

    // Should redirect to login on failure (no OAuth code), not 404
    $response->assertRedirect(route('login'));
});
