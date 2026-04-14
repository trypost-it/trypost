<?php

declare(strict_types=1);

use App\Enums\User\Setup;
use App\Enums\UserWorkspace\Role;
use App\Models\Brand;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    config(['trypost.self_hosted' => false]);

    $this->plan = Plan::first();
    $this->plan->update([
        'brand_limit' => 5,
        'member_limit' => 5,
    ]);

    $this->user = User::factory()->create(['setup' => Setup::Completed]);
    $this->workspace = Workspace::factory()->create([
        'user_id' => $this->user->id,
        'plan_id' => $this->plan->id,
    ]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Owner->value]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);
});

test('can create brand within limit', function () {
    Brand::factory()->count(3)->create(['workspace_id' => $this->workspace->id]);

    expect($this->user->can('create', [Brand::class, $this->workspace]))->toBeTrue();
});

test('cannot create brand beyond limit', function () {
    Brand::factory()->count(5)->create(['workspace_id' => $this->workspace->id]);

    expect($this->user->can('create', [Brand::class, $this->workspace]))->toBeFalse();
});

test('can invite member within limit', function () {
    expect($this->user->can('inviteMember', $this->workspace))->toBeTrue();
});

test('cannot invite member beyond limit', function () {
    $members = User::factory()->count(4)->create();

    foreach ($members as $member) {
        $this->workspace->members()->attach($member->id, ['role' => Role::Member->value]);
    }

    // 1 owner + 4 members = 5, which is the limit
    expect($this->user->can('inviteMember', $this->workspace))->toBeFalse();
});

test('self hosted mode bypasses brand limit', function () {
    config(['trypost.self_hosted' => true]);

    Brand::factory()->count(10)->create(['workspace_id' => $this->workspace->id]);

    expect($this->user->can('create', [Brand::class, $this->workspace]))->toBeTrue();
});

test('self hosted mode bypasses member limit', function () {
    config(['trypost.self_hosted' => true]);

    $members = User::factory()->count(10)->create();

    foreach ($members as $member) {
        $this->workspace->members()->attach($member->id, ['role' => Role::Member->value]);
    }

    expect($this->user->can('inviteMember', $this->workspace))->toBeTrue();
});
