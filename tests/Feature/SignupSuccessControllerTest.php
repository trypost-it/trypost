<?php

declare(strict_types=1);

use App\Models\User;

test('signup success page requires authentication', function () {
    $response = $this->get(route('register.success'));

    $response->assertRedirect(route('login'));
});

test('signup success page renders with default email provider', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('register.success'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('auth/SignupSuccess')
        ->where('authProvider', 'email')
    );
});

test('signup success page renders with google provider from session', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->withSession(['auth_provider' => 'google'])
        ->get(route('register.success'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('auth/SignupSuccess')
        ->where('authProvider', 'google')
    );
});
