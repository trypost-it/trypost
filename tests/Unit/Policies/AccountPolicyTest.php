<?php

declare(strict_types=1);

use App\Enums\UserWorkspace\Role;
use App\Models\Account;
use App\Models\Invite;
use App\Models\Plan;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Policies\AccountPolicy;

beforeEach(function () {
    $this->policy = new AccountPolicy;
    $this->account = Account::factory()->create();
    $this->owner = User::factory()->create(['account_id' => $this->account->id]);
    $this->account->update(['owner_id' => $this->owner->id]);
});

test('swapPlan allows account owner with usage fitting the target plan', function () {
    // Plus: workspace_limit=5, social_account_limit=10, member_limit=5
    $plan = Plan::where('slug', 'plus')->first();

    $response = $this->policy->swapPlan($this->owner, $this->account, $plan);

    expect($response->allowed())->toBeTrue();
});

test('swapPlan denies non-owner', function () {
    $member = User::factory()->create(['account_id' => $this->account->id]);
    $plan = Plan::where('slug', 'plus')->first();

    $response = $this->policy->swapPlan($member, $this->account, $plan);

    expect($response->denied())->toBeTrue();
    expect($response->message())->toBe(__('billing.flash.cannot_manage'));
});

test('swapPlan denies when workspace count exceeds target plan limit', function () {
    Workspace::factory()->count(3)->create([
        'account_id' => $this->account->id,
        'user_id' => $this->owner->id,
    ]);

    // Starter: workspace_limit=1
    $plan = Plan::where('slug', 'starter')->first();

    $response = $this->policy->swapPlan($this->owner, $this->account, $plan);

    expect($response->denied())->toBeTrue();
    expect($response->message())->toBe(__('billing.flash.cannot_downgrade.workspaces', [
        'plan' => $plan->name,
        'count' => '3',
        'limit' => '1',
    ]));
});

test('swapPlan denies when social account count exceeds target plan limit', function () {
    $workspace = Workspace::factory()->create([
        'account_id' => $this->account->id,
        'user_id' => $this->owner->id,
    ]);
    SocialAccount::factory()->count(6)->create(['workspace_id' => $workspace->id]);

    // Starter: social_account_limit=5
    $plan = Plan::where('slug', 'starter')->first();

    $response = $this->policy->swapPlan($this->owner, $this->account, $plan);

    expect($response->denied())->toBeTrue();
    expect($response->message())->toBe(__('billing.flash.cannot_downgrade.social_accounts', [
        'plan' => $plan->name,
        'count' => '6',
        'limit' => '5',
    ]));
});

test('swapPlan denies when members + pending invites exceed target plan limit', function () {
    User::factory()->count(2)->create(['account_id' => $this->account->id]);
    Invite::factory()->count(2)->create([
        'account_id' => $this->account->id,
        'invited_by' => $this->owner->id,
        'role' => Role::Member,
    ]);

    // Starter: member_limit=1
    $plan = Plan::where('slug', 'starter')->first();

    $response = $this->policy->swapPlan($this->owner, $this->account, $plan);

    expect($response->denied())->toBeTrue();
    expect($response->message())->toBe(__('billing.flash.cannot_downgrade.members', [
        'plan' => $plan->name,
        'count' => '5',
        'limit' => '1',
    ]));
});

test('swapPlan allows when usage equals target plan limit (boundary)', function () {
    Workspace::factory()->create([
        'account_id' => $this->account->id,
        'user_id' => $this->owner->id,
    ]);

    // Starter: workspace_limit=1, owner has exactly 1 workspace
    $plan = Plan::where('slug', 'starter')->first();

    $response = $this->policy->swapPlan($this->owner, $this->account, $plan);

    expect($response->allowed())->toBeTrue();
});
