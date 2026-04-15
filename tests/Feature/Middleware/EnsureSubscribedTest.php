<?php

declare(strict_types=1);

use App\Enums\User\Setup;
use App\Enums\UserWorkspace\Role;
use App\Models\Account;
use App\Models\User;
use App\Models\Workspace;

test('self hosted mode bypasses subscription check', function () {
    config(['trypost.self_hosted' => true]);

    $user = User::factory()->create(['setup' => Setup::Completed]);
    $workspace = Workspace::factory()->create(['user_id' => $user->id]);
    $workspace->members()->attach($user->id, ['role' => Role::Member->value]);
    $user->update(['current_workspace_id' => $workspace->id]);

    $this->actingAs($user)
        ->get(route('app.calendar'))
        ->assertOk();
});

test('unauthenticated user is redirected to login', function () {
    config(['trypost.self_hosted' => false]);

    $this->get(route('app.calendar'))
        ->assertRedirect(route('login'));
});

test('user with active subscription can access protected route', function () {
    config(['trypost.self_hosted' => false]);

    $user = User::factory()->create(['setup' => Setup::Completed]);
    $workspace = Workspace::factory()->create(['user_id' => $user->id]);
    $workspace->members()->attach($user->id, ['role' => Role::Member->value]);
    $user->update(['current_workspace_id' => $workspace->id]);

    $user->account->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_123',
        'stripe_status' => 'active',
        'stripe_price' => 'price_123',
    ]);

    $this->actingAs($user)
        ->get(route('app.calendar'))
        ->assertOk();
});

test('user on trial subscription can access protected route', function () {
    config(['trypost.self_hosted' => false]);

    $user = User::factory()->create(['setup' => Setup::Completed]);
    $workspace = Workspace::factory()->create(['user_id' => $user->id]);
    $workspace->members()->attach($user->id, ['role' => Role::Member->value]);
    $user->update(['current_workspace_id' => $workspace->id]);

    $user->account->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_trial_123',
        'stripe_status' => 'trialing',
        'stripe_price' => 'price_123',
        'trial_ends_at' => now()->addDays(7),
    ]);

    $this->actingAs($user)
        ->get(route('app.calendar'))
        ->assertOk();
});

test('user without subscription is redirected to subscribe page', function () {
    config(['trypost.self_hosted' => false]);

    $user = User::factory()->create(['setup' => Setup::Completed]);
    $workspace = Workspace::factory()->create(['user_id' => $user->id]);
    $workspace->members()->attach($user->id, ['role' => Role::Member->value]);
    $user->update(['current_workspace_id' => $workspace->id]);

    $this->actingAs($user)
        ->get(route('app.calendar'))
        ->assertRedirect(route('app.subscribe'));
});

test('user with expired trial subscription is redirected to subscribe page', function () {
    config(['trypost.self_hosted' => false]);

    $user = User::factory()->create(['setup' => Setup::Completed]);
    $workspace = Workspace::factory()->create(['user_id' => $user->id]);
    $workspace->members()->attach($user->id, ['role' => Role::Member->value]);
    $user->update(['current_workspace_id' => $workspace->id]);

    $user->account->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_expired_trial',
        'stripe_status' => 'canceled',
        'stripe_price' => 'price_123',
        'trial_ends_at' => now()->subDay(),
        'ends_at' => now()->subDay(),
    ]);

    $this->actingAs($user)
        ->get(route('app.calendar'))
        ->assertRedirect(route('app.subscribe'));
});

test('user with cancelled subscription is redirected to subscribe page', function () {
    config(['trypost.self_hosted' => false]);

    $user = User::factory()->create(['setup' => Setup::Completed]);
    $workspace = Workspace::factory()->create(['user_id' => $user->id]);
    $workspace->members()->attach($user->id, ['role' => Role::Member->value]);
    $user->update(['current_workspace_id' => $workspace->id]);

    $user->account->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_123',
        'stripe_status' => 'canceled',
        'stripe_price' => 'price_123',
        'ends_at' => now()->subDay(),
    ]);

    $this->actingAs($user)
        ->get(route('app.calendar'))
        ->assertRedirect(route('app.subscribe'));
});

test('member can access workspace when account has active subscription', function () {
    config(['trypost.self_hosted' => false]);

    $account = Account::factory()->create();
    $owner = User::factory()->create([
        'setup' => Setup::Completed,
        'account_id' => $account->id,
    ]);
    $account->update(['owner_id' => $owner->id]);
    $workspace = Workspace::factory()->create([
        'account_id' => $account->id,
        'user_id' => $owner->id,
    ]);
    $workspace->members()->attach($owner->id, ['role' => Role::Member->value]);

    $account->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_owner_123',
        'stripe_status' => 'active',
        'stripe_price' => 'price_123',
    ]);

    $member = User::factory()->create([
        'setup' => Setup::Completed,
        'account_id' => $account->id,
    ]);
    $workspace->members()->attach($member->id, ['role' => Role::Member->value]);
    $member->update(['current_workspace_id' => $workspace->id]);

    $this->actingAs($member)
        ->get(route('app.calendar'))
        ->assertOk();
});

test('member is redirected to subscribe when account has no subscription', function () {
    config(['trypost.self_hosted' => false]);

    $account = Account::factory()->create();
    $owner = User::factory()->create([
        'setup' => Setup::Completed,
        'account_id' => $account->id,
    ]);
    $account->update(['owner_id' => $owner->id]);
    $workspace = Workspace::factory()->create([
        'account_id' => $account->id,
        'user_id' => $owner->id,
    ]);

    $member = User::factory()->create([
        'setup' => Setup::Completed,
        'account_id' => $account->id,
    ]);
    $workspace->members()->attach($member->id, ['role' => Role::Member->value]);
    $member->update(['current_workspace_id' => $workspace->id]);

    $this->actingAs($member)
        ->get(route('app.calendar'))
        ->assertRedirect(route('app.subscribe'));
});
