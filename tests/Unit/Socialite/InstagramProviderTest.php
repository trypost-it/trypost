<?php

use App\Socialite\InstagramProvider;
use Illuminate\Http\Request;

test('instagram provider has correct scopes', function () {
    $request = Request::create('/');
    $provider = new InstagramProvider($request, 'client-id', 'client-secret', 'https://example.com/callback');

    $reflection = new ReflectionClass($provider);
    $property = $reflection->getProperty('scopes');
    $property->setAccessible(true);

    expect($property->getValue($provider))->toContain('instagram_business_basic');
    expect($property->getValue($provider))->toContain('instagram_business_content_publish');
});

test('instagram provider has correct token url', function () {
    $request = Request::create('/');
    $provider = new InstagramProvider($request, 'client-id', 'client-secret', 'https://example.com/callback');

    $reflection = new ReflectionClass($provider);
    $method = $reflection->getMethod('getTokenUrl');
    $method->setAccessible(true);

    expect($method->invoke($provider))->toBe('https://api.instagram.com/oauth/access_token');
});

test('instagram provider generates correct token fields', function () {
    $request = Request::create('/');
    $provider = new InstagramProvider($request, 'client-id', 'client-secret', 'https://example.com/callback');

    $reflection = new ReflectionClass($provider);
    $method = $reflection->getMethod('getTokenFields');
    $method->setAccessible(true);

    $fields = $method->invoke($provider, 'test-code');

    expect($fields['client_id'])->toBe('client-id');
    expect($fields['client_secret'])->toBe('client-secret');
    expect($fields['grant_type'])->toBe('authorization_code');
    expect($fields['redirect_uri'])->toBe('https://example.com/callback');
    expect($fields['code'])->toBe('test-code');
});

test('instagram provider maps user to object correctly', function () {
    $request = Request::create('/');
    $provider = new InstagramProvider($request, 'client-id', 'client-secret', 'https://example.com/callback');

    $reflection = new ReflectionClass($provider);
    $method = $reflection->getMethod('mapUserToObject');
    $method->setAccessible(true);

    $user = $method->invoke($provider, [
        'id' => '12345',
        'username' => 'testuser',
        'name' => 'Test User',
        'profile_picture_url' => 'https://example.com/avatar.jpg',
    ]);

    expect($user->getId())->toBe('12345');
    expect($user->getNickname())->toBe('testuser');
    expect($user->getName())->toBe('Test User');
    expect($user->getAvatar())->toBe('https://example.com/avatar.jpg');
});

test('instagram provider maps user without name uses username', function () {
    $request = Request::create('/');
    $provider = new InstagramProvider($request, 'client-id', 'client-secret', 'https://example.com/callback');

    $reflection = new ReflectionClass($provider);
    $method = $reflection->getMethod('mapUserToObject');
    $method->setAccessible(true);

    $user = $method->invoke($provider, [
        'id' => '12345',
        'username' => 'testuser',
    ]);

    expect($user->getName())->toBe('testuser');
});
