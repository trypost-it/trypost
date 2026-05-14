<?php

declare(strict_types=1);

use App\Actions\Invite\RemoveMember;
use App\Enums\UserWorkspace\Role;
use App\Models\Account;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\PlanSeeder;

beforeEach(function () {
    $this->seed(PlanSeeder::class);
});

test('removing a member nulls their current_workspace_id if pointing at this workspace', function () {
    $account = Account::factory()->create();
    $user = User::factory()->create(['account_id' => $account->id]);
    $workspace = Workspace::factory()->create([
        'account_id' => $account->id,
        'user_id' => $user->id,
    ]);
    $workspace->members()->attach($user->id, ['role' => Role::Member->value]);
    $user->update(['current_workspace_id' => $workspace->id]);

    RemoveMember::execute($workspace, (string) $user->id);

    expect($user->fresh()->current_workspace_id)->toBeNull();
});

test('removing a member does not affect their pointer if it was at a different workspace', function () {
    $account = Account::factory()->create();
    $user = User::factory()->create(['account_id' => $account->id]);
    $workspaceA = Workspace::factory()->create([
        'account_id' => $account->id,
        'user_id' => $user->id,
    ]);
    $workspaceB = Workspace::factory()->create([
        'account_id' => $account->id,
        'user_id' => $user->id,
    ]);
    $workspaceA->members()->attach($user->id, ['role' => Role::Member->value]);
    $workspaceB->members()->attach($user->id, ['role' => Role::Member->value]);
    $user->update(['current_workspace_id' => $workspaceB->id]);

    RemoveMember::execute($workspaceA, (string) $user->id);

    expect($user->fresh()->current_workspace_id)->toBe($workspaceB->id);
});

test('removing a member detaches them from the workspace', function () {
    $account = Account::factory()->create();
    $user = User::factory()->create(['account_id' => $account->id]);
    $workspace = Workspace::factory()->create([
        'account_id' => $account->id,
        'user_id' => $user->id,
    ]);
    $workspace->members()->attach($user->id, ['role' => Role::Member->value]);

    expect($workspace->members()->where('users.id', $user->id)->exists())->toBeTrue();

    RemoveMember::execute($workspace, (string) $user->id);

    expect($workspace->members()->where('users.id', $user->id)->exists())->toBeFalse();
});
