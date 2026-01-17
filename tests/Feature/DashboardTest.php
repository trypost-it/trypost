<?php

use App\Models\User;
use Laravel\Cashier\Subscription;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users without subscription are redirected to subscribe', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('subscribe'));
});

test('subscribed users without workspaces are redirected to create workspace', function () {
    $user = User::factory()->create();

    // Create an active subscription for the user
    Subscription::create([
        'user_id' => $user->id,
        'type' => 'default',
        'stripe_id' => 'sub_test_'.uniqid(),
        'stripe_status' => 'active',
        'stripe_price' => 'price_test',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('workspaces.create'));
});
