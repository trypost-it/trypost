<?php

declare(strict_types=1);

use App\Enums\User\Setup;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    $this->user = User::factory()->create(['setup' => Setup::Completed]);
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);
});

// Subscribe tests
test('subscribe requires authentication', function () {
    $response = $this->get(route('app.subscribe'));

    $response->assertRedirect(route('login'));
});

test('subscribe shows subscription page', function () {
    $response = $this->actingAs($this->user)->get(route('app.subscribe'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('billing/Subscribe', false)
        ->has('trialDays')
    );
});

// Index tests
test('billing index requires authentication', function () {
    $response = $this->get(route('app.billing.index'));

    $response->assertRedirect(route('login'));
});

test('billing index shows billing dashboard', function () {
    $response = $this->actingAs($this->user)->get(route('app.billing.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('billing/Index', false)
        ->has('hasSubscription')
        ->has('workspacesCount')
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
        ->has('userId')
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
    $response = $this->post(route('app.billing.checkout'));

    $response->assertRedirect(route('login'));
});

// Portal tests
test('portal requires authentication', function () {
    $response = $this->get(route('app.billing.portal'));

    $response->assertRedirect(route('login'));
});
