<?php

declare(strict_types=1);

use App\Enums\User\Setup;
use App\Enums\UserWorkspace\Role;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    $this->user = User::factory()->create(['setup' => Setup::Completed]);
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Owner->value]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);
});

// Subscribe tests
test('subscribe requires authentication', function () {
    $response = $this->get(route('app.subscribe'));

    $response->assertRedirect(route('login'));
});

test('subscribe shows subscription page', function () {
    config(['trypost.self_hosted' => false]);

    $response = $this->actingAs($this->user)->get(route('app.subscribe'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('billing/Subscribe', false)
        ->has('plans')
        ->has('trialDays')
    );
});

test('subscribe redirects to billing index when workspace has active subscription', function () {
    $this->workspace->subscriptions()->create([
        'type' => Workspace::SUBSCRIPTION_NAME,
        'stripe_id' => 'sub_test_'.fake()->uuid(),
        'stripe_status' => 'active',
        'stripe_price' => 'price_123',
    ]);

    $response = $this->actingAs($this->user)->get(route('app.subscribe'));

    $response->assertRedirect(route('app.billing.index'));
});

// Index tests
test('billing index requires authentication', function () {
    $response = $this->get(route('app.billing.index'));

    $response->assertRedirect(route('login'));
});

test('billing index shows billing dashboard', function () {
    $this->workspace->subscriptions()->create([
        'type' => Workspace::SUBSCRIPTION_NAME,
        'stripe_id' => 'sub_test_'.fake()->uuid(),
        'stripe_status' => 'active',
        'stripe_price' => 'price_123',
    ]);

    $response = $this->actingAs($this->user)->get(route('app.billing.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('billing/Index', false)
        ->has('hasSubscription')
        ->has('plan')
        ->has('plans')
    );
});

// Processing tests
test('billing processing requires authentication', function () {
    $response = $this->get(route('app.billing.processing'));

    $response->assertRedirect(route('login'));
});

test('billing processing shows processing page', function () {
    $response = $this->actingAs($this->user)->get(route('app.billing.processing'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('billing/Processing', false)
        ->has('workspaceId')
        ->has('status')
    );
});

test('billing processing accepts status parameter', function () {
    $response = $this->actingAs($this->user)->get(route('app.billing.processing', ['status' => 'success']));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('status', 'success')
    );
});

test('billing processing validates status parameter', function () {
    $response = $this->actingAs($this->user)->get(route('app.billing.processing', ['status' => 'invalid']));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('status', 'processing')
    );
});

// Checkout tests
test('checkout requires authentication', function () {
    $plan = Plan::first();
    $response = $this->post(route('app.billing.checkout', $plan));

    $response->assertRedirect(route('login'));
});

// Portal tests
test('portal requires authentication', function () {
    $response = $this->get(route('app.billing.portal'));

    $response->assertRedirect(route('login'));
});

// Authorization tests
test('admin cannot access billing index', function () {
    $admin = User::factory()->create(['setup' => Setup::Completed]);
    $this->workspace->members()->attach($admin->id, ['role' => Role::Admin->value]);
    $admin->update(['current_workspace_id' => $this->workspace->id]);

    $this->workspace->subscriptions()->create([
        'type' => Workspace::SUBSCRIPTION_NAME,
        'stripe_id' => 'sub_test_'.fake()->uuid(),
        'stripe_status' => 'active',
        'stripe_price' => 'price_123',
    ]);

    $this->actingAs($admin)->get(route('app.billing.index'))->assertForbidden();
});

test('member cannot access billing index', function () {
    $member = User::factory()->create(['setup' => Setup::Completed]);
    $this->workspace->members()->attach($member->id, ['role' => Role::Member->value]);
    $member->update(['current_workspace_id' => $this->workspace->id]);

    $this->workspace->subscriptions()->create([
        'type' => Workspace::SUBSCRIPTION_NAME,
        'stripe_id' => 'sub_test_'.fake()->uuid(),
        'stripe_status' => 'active',
        'stripe_price' => 'price_123',
    ]);

    $this->actingAs($member)->get(route('app.billing.index'))->assertForbidden();
});
