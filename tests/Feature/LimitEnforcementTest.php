<?php

declare(strict_types=1);

use App\Enums\User\Setup;
use App\Enums\UserWorkspace\Role;
use App\Models\Account;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    config(['trypost.self_hosted' => false]);

    $this->plan = Plan::first();
    $this->plan->update([
        'workspace_limit' => 5,
        'member_limit' => 5,
    ]);

    $this->account = Account::factory()->create(['plan_id' => $this->plan->id]);
    $this->user = User::factory()->create([
        'setup' => Setup::Completed,
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

test('can invite member within limit', function () {
    expect($this->user->can('inviteMember', $this->workspace))->toBeTrue();
});

test('cannot invite member beyond limit', function () {
    $members = User::factory()->count(4)->create([
        'account_id' => $this->account->id,
    ]);

    foreach ($members as $member) {
        $this->workspace->members()->attach($member->id, ['role' => Role::Member->value]);
    }

    // 1 owner + 4 members = 5, which is the limit
    expect($this->user->can('inviteMember', $this->workspace))->toBeFalse();
});

test('self hosted mode bypasses workspace limit', function () {
    config(['trypost.self_hosted' => true]);

    Workspace::factory()->count(10)->create([
        'account_id' => $this->account->id,
        'user_id' => $this->user->id,
    ]);

    expect($this->user->can('create', Workspace::class))->toBeTrue();
});

test('self hosted mode bypasses member limit', function () {
    config(['trypost.self_hosted' => true]);

    $members = User::factory()->count(10)->create([
        'account_id' => $this->account->id,
    ]);

    foreach ($members as $member) {
        $this->workspace->members()->attach($member->id, ['role' => Role::Member->value]);
    }

    expect($this->user->can('inviteMember', $this->workspace))->toBeTrue();
});
