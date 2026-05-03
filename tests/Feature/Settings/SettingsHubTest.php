<?php

declare(strict_types=1);

use App\Enums\UserWorkspace\Role;
use App\Models\User;
use App\Models\Workspace;

test('settings hub requires authentication', function () {
    $this->get(route('app.settings'))->assertRedirect(route('login'));
});

test('account owner with admin workspace role sees all three cards', function () {
    config()->set('trypost.self_hosted', false);

    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $user->id]);
    $workspace->members()->attach($user->id, ['role' => Role::Admin->value]);
    $user->update(['current_workspace_id' => $workspace->id]);

    $this->actingAs($user)->get(route('app.settings'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/Index')
            ->where('permissions.canManageProfile', true)
            ->where('permissions.canManageWorkspace', true)
            ->where('permissions.canManageAccount', true)
        );
});

test('plain workspace member only sees profile card', function () {
    $owner = User::factory()->create();
    $ownerWorkspace = Workspace::factory()->create(['user_id' => $owner->id]);

    $member = User::factory()->create();
    $ownerWorkspace->members()->attach($member->id, ['role' => Role::Member->value]);
    $member->update(['current_workspace_id' => $ownerWorkspace->id]);

    $this->actingAs($member)->get(route('app.settings'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('permissions.canManageProfile', true)
            ->where('permissions.canManageWorkspace', false)
            ->where('permissions.canManageAccount', false)
        );
});

test('workspace admin who is not account owner sees profile and workspace only', function () {
    $owner = User::factory()->create();
    $ownerWorkspace = Workspace::factory()->create(['user_id' => $owner->id]);

    $admin = User::factory()->create();
    $ownerWorkspace->members()->attach($admin->id, ['role' => Role::Admin->value]);
    $admin->update(['current_workspace_id' => $ownerWorkspace->id]);

    $this->actingAs($admin)->get(route('app.settings'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('permissions.canManageProfile', true)
            ->where('permissions.canManageWorkspace', true)
            ->where('permissions.canManageAccount', false)
        );
});

test('account card is hidden when self hosted', function () {
    config()->set('trypost.self_hosted', true);

    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $user->id]);
    $user->update(['current_workspace_id' => $workspace->id]);

    $this->actingAs($user)->get(route('app.settings'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('permissions.canManageAccount', false)
        );
});
