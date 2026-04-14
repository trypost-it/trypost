<?php

declare(strict_types=1);

use App\Enums\Plan\Slug as PlanSlug;
use App\Enums\User\Setup;
use App\Enums\UserWorkspace\Role;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    config(['trypost.self_hosted' => true]);

    $this->user = User::factory()->create(['setup' => Setup::Completed]);
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Owner->value]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);
});

test('workspace has active subscription in self hosted mode', function () {
    config(['trypost.self_hosted' => true]);

    expect($this->workspace->hasActiveSubscription())->toBeTrue();
});

test('workspace without subscription is not active in saas mode', function () {
    config(['trypost.self_hosted' => false]);

    expect($this->workspace->hasActiveSubscription())->toBeFalse();
});

test('workspace belongs to plan', function () {
    $plan = Plan::where('slug', PlanSlug::Starter)->first();
    $this->workspace->update(['plan_id' => $plan->id]);

    expect($this->workspace->fresh()->plan->id)->toBe($plan->id);
});

test('ensure subscribed middleware passes in self hosted mode', function () {
    config(['trypost.self_hosted' => true]);

    $response = $this->actingAs($this->user)->get(route('app.calendar'));

    $response->assertOk();
});

test('ensure subscribed middleware redirects without subscription in saas mode', function () {
    config(['trypost.self_hosted' => false]);

    $response = $this->actingAs($this->user)->get(route('app.calendar'));

    $response->assertRedirect(route('app.subscribe'));
});

test('billing page is accessible by workspace owner', function () {
    config(['trypost.self_hosted' => false]);

    $this->workspace->subscriptions()->create([
        'type' => Workspace::SUBSCRIPTION_NAME,
        'stripe_id' => 'sub_test_'.fake()->uuid(),
        'stripe_status' => 'active',
        'stripe_price' => 'price_123',
    ]);

    $response = $this->actingAs($this->user)->get(route('app.billing.index'));

    $response->assertOk();
});

test('billing page is not accessible by workspace member', function () {
    config(['trypost.self_hosted' => false]);

    $member = User::factory()->create(['setup' => Setup::Completed]);
    $this->workspace->members()->attach($member->id, ['role' => Role::Member->value]);
    $member->update(['current_workspace_id' => $this->workspace->id]);

    $this->workspace->subscriptions()->create([
        'type' => Workspace::SUBSCRIPTION_NAME,
        'stripe_id' => 'sub_test_'.fake()->uuid(),
        'stripe_status' => 'active',
        'stripe_price' => 'price_123',
    ]);

    $response = $this->actingAs($member)->get(route('app.billing.index'));

    $response->assertForbidden();
});

test('subscribe page shows plans', function () {
    config(['trypost.self_hosted' => false]);

    $activePlanCount = Plan::active()->count();

    $response = $this->actingAs($this->user)->get(route('app.subscribe'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('billing/Subscribe', false)
        ->has('plans', $activePlanCount)
        ->has('trialDays')
    );
});

test('stripe email returns workspace owner email', function () {
    expect($this->workspace->stripeEmail())->toBe($this->user->email);
});

test('stripe name returns workspace name', function () {
    expect($this->workspace->stripeName())->toBe($this->workspace->name);
});
