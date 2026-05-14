<?php

declare(strict_types=1);

use App\Enums\Plan\Slug as PlanSlug;
use App\Enums\UserWorkspace\Role;
use App\Models\Account;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    config(['trypost.self_hosted' => true]);

    $this->account = Account::factory()->create();
    $this->user = User::factory()->create([
        'account_id' => $this->account->id,
    ]);
    $this->account->update(['owner_id' => $this->user->id]);
    $this->workspace = Workspace::factory()->create([
        'account_id' => $this->account->id,
        'user_id' => $this->user->id,
    ]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Member->value]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);
});

test('account has active subscription in self hosted mode', function () {
    config(['trypost.self_hosted' => true]);

    expect($this->account->hasActiveSubscription())->toBeTrue();
});

test('account without subscription is not active in saas mode', function () {
    config(['trypost.self_hosted' => false]);

    expect($this->account->hasActiveSubscription())->toBeFalse();
});

test('account belongs to plan', function () {
    $plan = Plan::where('slug', PlanSlug::Starter)->first();
    $this->account->update(['plan_id' => $plan->id]);

    expect($this->account->fresh()->plan->id)->toBe($plan->id);
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

test('billing page is accessible by account owner', function () {
    config(['trypost.self_hosted' => false]);

    $this->account->subscriptions()->create([
        'type' => Account::SUBSCRIPTION_NAME,
        'stripe_id' => 'sub_test_'.fake()->uuid(),
        'stripe_status' => 'active',
        'stripe_price' => 'price_123',
    ]);

    $response = $this->actingAs($this->user)->get(route('app.billing.index'));

    $response->assertOk();
});

test('billing page is not accessible by non-owner member', function () {
    config(['trypost.self_hosted' => false]);

    $member = User::factory()->create([
        'account_id' => $this->account->id,
    ]);
    $this->workspace->members()->attach($member->id, ['role' => Role::Member->value]);
    $member->update(['current_workspace_id' => $this->workspace->id]);

    $this->account->subscriptions()->create([
        'type' => Account::SUBSCRIPTION_NAME,
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
    );
});

test('stripe email returns account owner email', function () {
    expect($this->account->stripeEmail())->toBe($this->user->email);
});

test('stripe name returns account name', function () {
    expect($this->account->stripeName())->toBe($this->account->name);
});

test('create workspace is blocked when account hits plan limit in saas mode', function () {
    config(['trypost.self_hosted' => false]);

    $starter = Plan::where('slug', PlanSlug::Starter)->first();
    $this->account->update(['plan_id' => $starter->id]);
    $this->account->subscriptions()->create([
        'type' => Account::SUBSCRIPTION_NAME,
        'stripe_id' => 'sub_test_'.fake()->uuid(),
        'stripe_status' => 'active',
        'stripe_price' => 'price_123',
    ]);

    // Starter plan: workspace_limit = 1; the user already owns 1 workspace (from beforeEach).
    $response = $this->actingAs($this->user)
        ->from(route('app.calendar'))
        ->get(route('app.workspaces.create'));

    $response->assertRedirect(route('app.calendar'))
        ->assertSessionHas('flash.error', __('workspaces.limit_reached'));
});

test('create workspace is allowed when below plan limit in saas mode', function () {
    config(['trypost.self_hosted' => false]);

    $pro = Plan::where('slug', PlanSlug::Pro)->first();
    $this->account->update(['plan_id' => $pro->id]);
    $this->account->subscriptions()->create([
        'type' => Account::SUBSCRIPTION_NAME,
        'stripe_id' => 'sub_test_'.fake()->uuid(),
        'stripe_status' => 'active',
        'stripe_price' => 'price_123',
    ]);

    // Pro plan: workspace_limit = 15; account has 1 workspace.
    $response = $this->actingAs($this->user->fresh())->get(route('app.workspaces.create'));

    $response->assertOk();
});

test('store workspace is blocked when account hits plan limit in saas mode', function () {
    config(['trypost.self_hosted' => false]);

    $starter = Plan::where('slug', PlanSlug::Starter)->first();
    $this->account->update(['plan_id' => $starter->id]);
    $this->account->subscriptions()->create([
        'type' => Account::SUBSCRIPTION_NAME,
        'stripe_id' => 'sub_test_'.fake()->uuid(),
        'stripe_status' => 'active',
        'stripe_price' => 'price_123',
    ]);

    // Starter plan: workspace_limit = 1; the user already owns 1 workspace.
    $response = $this->actingAs($this->user)->post(route('app.workspaces.store'), [
        'name' => 'Second workspace',
    ]);

    $response->assertForbidden();
});

test('workspace limit is bypassed in self-hosted mode', function () {
    config(['trypost.self_hosted' => true]);

    $starter = Plan::where('slug', PlanSlug::Starter)->first();
    $this->account->update(['plan_id' => $starter->id]);

    // Even on Starter (limit 1) with an existing workspace, self-hosted lets through.
    $response = $this->actingAs($this->user)->get(route('app.workspaces.create'));

    $response->assertOk();
});
