<?php

declare(strict_types=1);

use App\Enums\UserWorkspace\Role;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    $this->user = User::factory()->create([]);
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->user->workspaces()->attach($this->workspace->id, ['role' => Role::Member->value]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);
});

test('toggle active account to inactive', function () {
    $account = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)->put(route('app.accounts.toggle', $account));

    $response->assertRedirect();
    expect($account->fresh()->is_active)->toBeFalse();
});

test('toggle inactive account to active', function () {
    $account = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'is_active' => false,
    ]);

    $response = $this->actingAs($this->user)->put(route('app.accounts.toggle', $account));

    $response->assertRedirect();
    expect($account->fresh()->is_active)->toBeTrue();
});

test('cannot toggle account from another workspace', function () {
    $otherWorkspace = Workspace::factory()->create();
    $account = SocialAccount::factory()->create([
        'workspace_id' => $otherWorkspace->id,
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)->put(route('app.accounts.toggle', $account));

    $response->assertForbidden();
});

test('member cannot toggle account', function () {
    $member = User::factory()->create([]);
    $this->workspace->members()->attach($member->id, ['role' => Role::Member->value]);
    $member->update(['current_workspace_id' => $this->workspace->id]);

    $account = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'is_active' => true,
    ]);

    $response = $this->actingAs($member)->put(route('app.accounts.toggle', $account));

    $response->assertForbidden();
});
