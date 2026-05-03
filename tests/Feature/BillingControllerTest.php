<?php

declare(strict_types=1);

use App\Enums\UserWorkspace\Role;
use App\Models\Account;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
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

test('subscribe redirects to billing index when account has active subscription', function () {
    config(['trypost.self_hosted' => false]);

    $this->account->subscriptions()->create([
        'type' => Account::SUBSCRIPTION_NAME,
        'stripe_id' => 'sub_test_'.fake()->uuid(),
        'stripe_status' => 'active',
        'stripe_price' => 'price_123',
    ]);

    $response = $this->actingAs($this->user)->get(route('app.subscribe'));

    $response->assertRedirect(route('app.billing.index'));
});

test('subscribe redirects to calendar in self hosted mode', function () {
    config(['trypost.self_hosted' => true]);

    $response = $this->actingAs($this->user)->get(route('app.subscribe'));

    $response->assertRedirect(route('app.calendar'));
});

// Index tests
test('billing index requires authentication', function () {
    $response = $this->get(route('app.billing.index'));

    $response->assertRedirect(route('login'));
});

test('billing index shows billing dashboard', function () {
    config(['trypost.self_hosted' => false]);

    $this->account->subscriptions()->create([
        'type' => Account::SUBSCRIPTION_NAME,
        'stripe_id' => 'sub_test_'.fake()->uuid(),
        'stripe_status' => 'active',
        'stripe_price' => 'price_123',
    ]);

    $response = $this->actingAs($this->user)->get(route('app.billing.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('settings/account/Billing', false)
        ->has('hasSubscription')
        ->has('plan')
        ->has('plans')
    );
});

test('billing index redirects to calendar in self hosted mode', function () {
    config(['trypost.self_hosted' => true]);

    $response = $this->actingAs($this->user)->get(route('app.billing.index'));

    $response->assertRedirect(route('app.calendar'));
});

// Processing tests
test('billing processing requires authentication', function () {
    $response = $this->get(route('app.billing.processing'));

    $response->assertRedirect(route('login'));
});

test('billing processing shows processing page', function () {
    config(['trypost.self_hosted' => false]);

    $response = $this->actingAs($this->user)->get(route('app.billing.processing'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('billing/Processing', false)
        ->has('subscriptionActive')
    );
});

test('billing processing redirects to calendar in self hosted mode', function () {
    config(['trypost.self_hosted' => true]);

    $response = $this->actingAs($this->user)->get(route('app.billing.processing'));

    $response->assertRedirect(route('app.calendar'));
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
test('non-owner admin cannot access billing index', function () {
    config(['trypost.self_hosted' => false]);

    $admin = User::factory()->create([
        'account_id' => $this->account->id,
    ]);
    $this->workspace->members()->attach($admin->id, ['role' => Role::Admin->value]);
    $admin->update(['current_workspace_id' => $this->workspace->id]);

    $this->account->subscriptions()->create([
        'type' => Account::SUBSCRIPTION_NAME,
        'stripe_id' => 'sub_test_'.fake()->uuid(),
        'stripe_status' => 'active',
        'stripe_price' => 'price_123',
    ]);

    $this->actingAs($admin)->get(route('app.billing.index'))->assertForbidden();
});

test('member cannot access billing index', function () {
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

    $this->actingAs($member)->get(route('app.billing.index'))->assertForbidden();
});

// Swap tests
test('swap blocks downgrade when usage exceeds target plan limits', function () {
    config(['trypost.self_hosted' => false]);

    $currentPlan = Plan::where('slug', 'plus')->first();
    $currentPlan->update([
        'stripe_monthly_price_id' => 'price_current_monthly',
        'stripe_yearly_price_id' => 'price_current_yearly',
    ]);
    $this->account->update(['plan_id' => $currentPlan->id]);

    $this->account->subscriptions()->create([
        'type' => Account::SUBSCRIPTION_NAME,
        'stripe_id' => 'sub_test_'.fake()->uuid(),
        'stripe_status' => 'active',
        'stripe_price' => 'price_current_monthly',
    ]);

    Workspace::factory()->count(3)->create([
        'account_id' => $this->account->id,
        'user_id' => $this->user->id,
    ]);

    // Starter: workspace_limit=1
    $targetPlan = Plan::where('slug', 'starter')->first();
    $targetPlan->update([
        'stripe_monthly_price_id' => 'price_target_monthly',
        'stripe_yearly_price_id' => 'price_target_yearly',
    ]);

    $this->user->unsetRelation('account');

    $response = $this->actingAs($this->user)
        ->from(route('app.billing.index'))
        ->post(route('app.billing.swap', $targetPlan), [
            'price_id' => 'price_target_monthly',
        ]);

    $response->assertRedirect(route('app.billing.index'));
    $response->assertSessionHas('flash.error', __('billing.flash.cannot_downgrade.workspaces', [
        'plan' => $targetPlan->name,
        'count' => '4', // 3 created + 1 from beforeEach
        'limit' => '1',
    ]));
});

test('swap blocks yearly to monthly downgrade', function () {
    config(['trypost.self_hosted' => false]);

    $plan = Plan::where('slug', 'max')->first();
    $plan->update([
        'stripe_monthly_price_id' => 'price_monthly',
        'stripe_yearly_price_id' => 'price_yearly',
    ]);
    $this->account->update(['plan_id' => $plan->id]);

    $this->user->unsetRelation('account');

    $this->account->subscriptions()->create([
        'type' => Account::SUBSCRIPTION_NAME,
        'stripe_id' => 'sub_test_'.fake()->uuid(),
        'stripe_status' => 'active',
        'stripe_price' => 'price_yearly',
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('app.billing.swap', $plan), [
            'price_id' => 'price_monthly',
        ]);

    $response->assertStatus(422);
});

test('swap rejects invalid price_id for plan', function () {
    config(['trypost.self_hosted' => false]);

    $plan = Plan::where('slug', 'pro')->first();
    $plan->update([
        'stripe_monthly_price_id' => 'price_monthly',
        'stripe_yearly_price_id' => 'price_yearly',
    ]);

    $this->account->subscriptions()->create([
        'type' => Account::SUBSCRIPTION_NAME,
        'stripe_id' => 'sub_test_'.fake()->uuid(),
        'stripe_status' => 'active',
        'stripe_price' => 'price_other',
    ]);

    $this->user->unsetRelation('account');

    $response = $this->actingAs($this->user)
        ->post(route('app.billing.swap', $plan), [
            'price_id' => 'price_unrelated',
        ]);

    $response->assertStatus(422);
});

test('swap requires authentication', function () {
    $plan = Plan::first();
    $response = $this->post(route('app.billing.swap', $plan));

    $response->assertRedirect(route('login'));
});
