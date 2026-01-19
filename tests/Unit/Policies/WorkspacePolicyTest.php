<?php

use App\Enums\UserWorkspace\Role;
use App\Models\User;
use App\Models\Workspace;
use App\Policies\WorkspacePolicy;

beforeEach(function () {
    $this->policy = new WorkspacePolicy;
});

test('any user can view any workspaces', function () {
    $user = User::factory()->create();

    expect($this->policy->viewAny($user))->toBeTrue();
});

test('owner can view workspace', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $user->id]);

    expect($this->policy->view($user, $workspace))->toBeTrue();
});

test('member can view workspace', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $owner->id]);
    $workspace->members()->attach($member->id, ['role' => Role::Member->value]);

    expect($this->policy->view($member, $workspace))->toBeTrue();
});

test('non member cannot view workspace', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $owner->id]);

    expect($this->policy->view($otherUser, $workspace))->toBeFalse();
});

test('any user can create workspace', function () {
    $user = User::factory()->create();

    expect($this->policy->create($user))->toBeTrue();
});

test('owner can update workspace', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $user->id]);

    expect($this->policy->update($user, $workspace))->toBeTrue();
});

test('admin can update workspace', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $owner->id]);
    $workspace->members()->attach($admin->id, ['role' => Role::Admin->value]);

    expect($this->policy->update($admin, $workspace))->toBeTrue();
});

test('member cannot update workspace', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $owner->id]);
    $workspace->members()->attach($member->id, ['role' => Role::Member->value]);

    expect($this->policy->update($member, $workspace))->toBeFalse();
});

test('only owner can delete workspace', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $owner->id]);
    $workspace->members()->attach($admin->id, ['role' => Role::Admin->value]);

    expect($this->policy->delete($owner, $workspace))->toBeTrue();
    expect($this->policy->delete($admin, $workspace))->toBeFalse();
});

test('only owner can restore workspace', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $owner->id]);
    $workspace->members()->attach($admin->id, ['role' => Role::Admin->value]);

    expect($this->policy->restore($owner, $workspace))->toBeTrue();
    expect($this->policy->restore($admin, $workspace))->toBeFalse();
});

test('only owner can force delete workspace', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $owner->id]);
    $workspace->members()->attach($admin->id, ['role' => Role::Admin->value]);

    expect($this->policy->forceDelete($owner, $workspace))->toBeTrue();
    expect($this->policy->forceDelete($admin, $workspace))->toBeFalse();
});

test('owner and admin can manage team', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $owner->id]);
    $workspace->members()->attach($admin->id, ['role' => Role::Admin->value]);
    $workspace->members()->attach($member->id, ['role' => Role::Member->value]);

    expect($this->policy->manageTeam($owner, $workspace))->toBeTrue();
    expect($this->policy->manageTeam($admin, $workspace))->toBeTrue();
    expect($this->policy->manageTeam($member, $workspace))->toBeFalse();
});

test('owner and admin can manage accounts', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $owner->id]);
    $workspace->members()->attach($admin->id, ['role' => Role::Admin->value]);
    $workspace->members()->attach($member->id, ['role' => Role::Member->value]);

    expect($this->policy->manageAccounts($owner, $workspace))->toBeTrue();
    expect($this->policy->manageAccounts($admin, $workspace))->toBeTrue();
    expect($this->policy->manageAccounts($member, $workspace))->toBeFalse();
});

test('owner and member can create post', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $otherUser = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $owner->id]);
    $workspace->members()->attach($member->id, ['role' => Role::Member->value]);

    expect($this->policy->createPost($owner, $workspace))->toBeTrue();
    expect($this->policy->createPost($member, $workspace))->toBeTrue();
    expect($this->policy->createPost($otherUser, $workspace))->toBeFalse();
});
