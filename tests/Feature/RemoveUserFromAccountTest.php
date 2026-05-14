<?php

declare(strict_types=1);

use App\Enums\Plan\Slug;
use App\Enums\UserWorkspace\Role;
use App\Models\Account;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\PlanSeeder;

beforeEach(function () {
    config(['trypost.self_hosted' => false]);
    $this->seed(PlanSeeder::class);

    $plan = Plan::where('slug', Slug::Pro)->firstOrFail();
    $this->account = Account::factory()->create(['plan_id' => $plan->id]);
    $this->owner = User::factory()->create(['account_id' => $this->account->id]);
    $this->account->update(['owner_id' => $this->owner->id]);

    // Give the owner a workspace so EnsureAccountReady middleware passes
    $this->workspace = Workspace::factory()->create([
        'account_id' => $this->account->id,
        'user_id' => $this->owner->id,
    ]);
    $this->workspace->members()->attach($this->owner->id, ['role' => Role::Member->value]);
    $this->owner->update(['current_workspace_id' => $this->workspace->id]);
    $this->owner = $this->owner->fresh();
});

test('owner can remove a member from account', function () {
    $member = User::factory()->create(['account_id' => $this->account->id]);
    $this->workspace->members()->attach($member->id, ['role' => Role::Member->value]);

    expect($this->account->users()->count())->toBe(2);

    $this->actingAs($this->owner)
        ->delete(route('app.account.members.remove', $member->id))
        ->assertRedirect();

    expect($this->account->users()->count())->toBe(1);
    expect(User::find($member->id))->toBeNull();
});

test('owner cannot be removed', function () {
    $this->actingAs($this->owner)
        ->delete(route('app.account.members.remove', $this->owner->id))
        ->assertSessionHasErrors('user');

    expect(User::find($this->owner->id))->not->toBeNull();
});

test('non-owner cannot remove members', function () {
    $member = User::factory()->create(['account_id' => $this->account->id]);
    $this->workspace->members()->attach($member->id, ['role' => Role::Member->value]);
    $member->update(['current_workspace_id' => $this->workspace->id]);

    $otherMember = User::factory()->create(['account_id' => $this->account->id]);
    $this->workspace->members()->attach($otherMember->id, ['role' => Role::Member->value]);

    $this->actingAs($member->fresh())
        ->delete(route('app.account.members.remove', $otherMember->id))
        ->assertForbidden();

    expect(User::find($otherMember->id))->not->toBeNull();
});

test('cannot remove user from different account', function () {
    $otherAccount = Account::factory()->create();
    $otherUser = User::factory()->create(['account_id' => $otherAccount->id]);

    $this->actingAs($this->owner)
        ->delete(route('app.account.members.remove', $otherUser->id))
        ->assertSessionHasErrors('user');

    expect(User::find($otherUser->id))->not->toBeNull();
});

test('requires authentication to remove a member from account', function () {
    $member = User::factory()->create(['account_id' => $this->account->id]);

    $this->delete(route('app.account.members.remove', $member->id))
        ->assertRedirect(route('login'));
});

test('removed user no longer appears in workspace members', function () {
    $member = User::factory()->create(['account_id' => $this->account->id]);
    $this->workspace->members()->attach($member->id, ['role' => Role::Member->value]);

    expect($this->workspace->members()->where('users.id', $member->id)->exists())->toBeTrue();

    $this->actingAs($this->owner->fresh())
        ->delete(route('app.account.members.remove', $member->id))
        ->assertRedirect();

    expect($this->workspace->members()->where('users.id', $member->id)->exists())->toBeFalse();
});
