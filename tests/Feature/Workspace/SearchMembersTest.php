<?php

declare(strict_types=1);

use App\Enums\UserWorkspace\Role;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    $this->user = User::factory()->create(['name' => 'Alice Owner']);
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Member->value]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);
});

test('returns workspace members excluding the current user', function () {
    $bob = User::factory()->create(['name' => 'Bob Builder']);
    $this->workspace->members()->attach($bob->id, ['role' => Role::Member->value]);

    $response = $this->actingAs($this->user)->getJson(route('app.workspace.members.search'));

    $response->assertOk();
    $response->assertJsonCount(1);
    $response->assertJsonPath('0.id', $bob->id);
    $names = collect($response->json())->pluck('name')->all();
    expect($names)->not->toContain('Alice Owner');
    expect($names)->toContain('Bob Builder');
});

test('filters by query case-insensitively', function () {
    $bob = User::factory()->create(['name' => 'Bob Builder']);
    $this->workspace->members()->attach($bob->id, ['role' => Role::Member->value]);

    $response = $this->actingAs($this->user)->getJson(route('app.workspace.members.search', ['q' => 'bob']));

    $response->assertOk();
    $response->assertJsonCount(1);
    $response->assertJsonPath('0.id', $bob->id);
});

test('does not include users from other workspaces', function () {
    $other = User::factory()->create(['name' => 'Eve External']);
    $otherWorkspace = Workspace::factory()->create(['user_id' => $other->id]);
    $otherWorkspace->members()->attach($other->id, ['role' => Role::Member->value]);

    $response = $this->actingAs($this->user)->getJson(route('app.workspace.members.search'));

    $response->assertOk();
    $names = collect($response->json())->pluck('name')->all();
    expect($names)->not->toContain('Eve External');
});

test('requires authentication', function () {
    $this->getJson(route('app.workspace.members.search'))->assertStatus(401);
});
