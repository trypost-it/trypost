<?php

use App\Enums\User\Setup;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    $this->user = User::factory()->create([
        'setup' => Setup::Completed,
    ]);
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);
});

test('billing index shows subscription info for subscribed user', function () {
    $this->user->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_123',
        'stripe_status' => 'active',
        'stripe_price' => 'price_123',
        'quantity' => 2,
    ]);

    $response = $this->actingAs($this->user)->get(route('billing.index'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('billing/Index')
        ->has('hasSubscription')
        ->has('subscription')
        ->has('workspacesCount')
    );
});

test('billing index shows info for user without subscription', function () {
    $response = $this->actingAs($this->user)->get(route('billing.index'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('billing/Index')
        ->where('hasSubscription', false)
    );
});

test('billing index shows trial info when on trial', function () {
    $this->user->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_123',
        'stripe_status' => 'trialing',
        'stripe_price' => 'price_123',
        'quantity' => 1,
        'trial_ends_at' => now()->addDays(14),
    ]);

    $response = $this->actingAs($this->user)->get(route('billing.index'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('billing/Index')
        ->where('onTrial', true)
        ->has('trialEndsAt')
    );
});

test('processing page shows for user during checkout', function () {
    $response = $this->actingAs($this->user)->get(route('billing.processing', ['status' => 'success']));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('billing/Processing')
        ->has('userId')
        ->where('status', 'success')
    );
});

test('processing page shows cancelled status', function () {
    $response = $this->actingAs($this->user)->get(route('billing.processing', ['status' => 'cancelled']));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->where('status', 'cancelled')
    );
});

test('processing page defaults to processing status for invalid status', function () {
    $response = $this->actingAs($this->user)->get(route('billing.processing', ['status' => 'invalid']));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->where('status', 'processing')
    );
});
