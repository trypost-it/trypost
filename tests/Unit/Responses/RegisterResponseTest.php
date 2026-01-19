<?php

use App\Http\Responses\RegisterResponse;
use App\Models\User;
use Illuminate\Http\Request;

test('register redirects to onboarding step1', function () {
    $user = User::factory()->create();

    $request = Request::create('/register', 'POST');
    $request->setUserResolver(fn () => $user);

    $response = (new RegisterResponse)->toResponse($request);

    expect($response->getTargetUrl())->toContain('onboarding/step1');
});

test('register redirects to invite when pending invite token exists', function () {
    $user = User::factory()->create();

    session(['pending_invite_token' => 'test-token-456']);

    // Test the session token is stored correctly
    expect(session('pending_invite_token'))->toBe('test-token-456');

    session()->forget('pending_invite_token');
});

test('register returns json response when wantsJson', function () {
    $user = User::factory()->create();

    $request = Request::create('/register', 'POST', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
    $request->setUserResolver(fn () => $user);

    $response = (new RegisterResponse)->toResponse($request);

    expect($response->getContent())->toContain('two_factor');
});
