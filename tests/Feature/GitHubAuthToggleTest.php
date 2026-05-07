<?php

declare(strict_types=1);

test('login page shares github auth enabled prop as false when disabled', function () {
    config(['postpro.github_auth_enabled' => false]);

    $response = $this->get(route('login'));

    $response->assertOk();

    $page = $response->original->getData()['page'];
    expect($page['props']['githubAuthEnabled'])->toBeFalse();
});

test('login page shares github auth enabled prop as true when enabled', function () {
    config(['postpro.github_auth_enabled' => true]);

    $response = $this->get(route('login'));

    $response->assertOk();

    $page = $response->original->getData()['page'];
    expect($page['props']['githubAuthEnabled'])->toBeTrue();
});

test('register page shares github auth enabled prop', function () {
    config(['postpro.github_auth_enabled' => true]);

    $response = $this->get(route('register'));

    $response->assertOk();

    $page = $response->original->getData()['page'];
    expect($page['props']['githubAuthEnabled'])->toBeTrue();
});

test('github auth redirect route exists', function () {
    config(['services.github.client_id' => 'test-id']);
    config(['services.github.client_secret' => 'test-secret']);
    config(['services.github.redirect' => 'https://app.postpro.test/auth/github/callback']);

    $response = $this->get(route('auth.github.redirect'));

    // Should redirect to GitHub OAuth, not 404
    $response->assertRedirect();
});

test('github auth callback route exists', function () {
    $response = $this->get(route('auth.github.callback'));

    // Should redirect to login on failure (no OAuth code), not 404
    $response->assertRedirect(route('login'));
});

