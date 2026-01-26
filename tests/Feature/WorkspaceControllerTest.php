<?php

use App\Enums\User\Setup;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    $this->user = User::factory()->create(['setup' => Setup::Completed]);
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->workspace->members()->attach($this->user->id, ['role' => 'owner']);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);
});

// Index tests
test('workspaces index requires authentication', function () {
    $response = $this->get(route('workspaces.index'));

    $response->assertRedirect(route('login'));
});

test('workspaces index shows all workspaces for user', function () {
    $workspaces = Workspace::factory()->count(2)->create(['user_id' => $this->user->id]);
    foreach ($workspaces as $workspace) {
        $workspace->members()->attach($this->user->id, ['role' => 'owner']);
    }

    $response = $this->actingAs($this->user)->get(route('workspaces.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('workspaces/Index', false)
        ->has('workspaces', 3)
        ->has('currentWorkspaceId')
    );
});

// Create tests
test('create workspace requires authentication', function () {
    $response = $this->get(route('workspaces.create'));

    $response->assertRedirect(route('login'));
});

test('create workspace shows form for user with no workspaces', function () {
    // Delete existing workspace so user has none
    $this->user->update(['current_workspace_id' => null]);
    $this->workspace->delete();

    $response = $this->actingAs($this->user)->get(route('workspaces.create'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('workspaces/Create', false)
    );
});

test('create workspace shows form when user already has workspace in self-hosted mode', function () {
    config(['trypost.self_hosted' => true]);

    $response = $this->actingAs($this->user)->get(route('workspaces.create'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('workspaces/Create', false)
    );
});

// Store tests
test('store workspace requires authentication', function () {
    $response = $this->post(route('workspaces.store'), ['name' => 'Test Workspace']);

    $response->assertRedirect(route('login'));
});

test('store workspace creates first workspace', function () {
    // Delete existing workspace so user has none
    $this->user->update(['current_workspace_id' => null]);
    $this->workspace->delete();

    $response = $this->actingAs($this->user)->post(route('workspaces.store'), [
        'name' => 'New Workspace',
    ]);

    $response->assertRedirect(route('calendar'));

    $this->assertDatabaseHas('workspaces', [
        'name' => 'New Workspace',
        'user_id' => $this->user->id,
    ]);
});

test('store workspace creates second workspace in self-hosted mode', function () {
    config(['trypost.self_hosted' => true]);

    $response = $this->actingAs($this->user)->post(route('workspaces.store'), [
        'name' => 'Second Workspace',
    ]);

    $response->assertRedirect(route('calendar'));

    $this->assertDatabaseHas('workspaces', [
        'name' => 'Second Workspace',
        'user_id' => $this->user->id,
    ]);
});

test('store workspace validates name is required', function () {
    $response = $this->actingAs($this->user)->post(route('workspaces.store'), [
        'name' => '',
    ]);

    $response->assertSessionHasErrors('name');
});

test('store workspace sets new workspace as current', function () {
    // Delete existing workspace so user has none
    $this->user->update(['current_workspace_id' => null]);
    $this->workspace->delete();

    $this->actingAs($this->user)->post(route('workspaces.store'), [
        'name' => 'New Workspace',
    ]);

    $this->user->refresh();
    $newWorkspace = Workspace::where('name', 'New Workspace')->first();

    expect($this->user->current_workspace_id)->toBe($newWorkspace->id);
});

// Switch tests
test('switch workspace requires authentication', function () {
    $response = $this->post(route('workspaces.switch', $this->workspace));

    $response->assertRedirect(route('login'));
});

test('switch workspace changes current workspace', function () {
    $otherWorkspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $otherWorkspace->members()->attach($this->user->id, ['role' => 'owner']);

    $response = $this->actingAs($this->user)->post(route('workspaces.switch', $otherWorkspace));

    $response->assertRedirect(route('calendar'));

    $this->user->refresh();
    expect($this->user->current_workspace_id)->toBe($otherWorkspace->id);
});

test('switch workspace returns 403 for workspace user does not belong to', function () {
    $otherUser = User::factory()->create(['setup' => Setup::Completed]);
    $otherWorkspace = Workspace::factory()->create(['user_id' => $otherUser->id]);

    $response = $this->actingAs($this->user)->post(route('workspaces.switch', $otherWorkspace));

    $response->assertForbidden();
});

// Settings tests
test('workspace settings requires authentication', function () {
    $response = $this->get(route('workspace.settings'));

    $response->assertRedirect(route('login'));
});

test('workspace settings shows settings page', function () {
    $response = $this->actingAs($this->user)->get(route('workspace.settings'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('settings/Workspace', false)
        ->has('workspace')
        ->has('timezones')
    );
});

test('workspace settings redirects to create if no workspace', function () {
    $this->user->update(['current_workspace_id' => null]);

    $response = $this->actingAs($this->user)->get(route('workspace.settings'));

    $response->assertRedirect(route('workspaces.create'));
});

// Update settings tests
test('update workspace settings requires authentication', function () {
    $response = $this->put(route('workspace.settings.update'), [
        'name' => 'Updated Name',
        'timezone' => 'America/New_York',
    ]);

    $response->assertRedirect(route('login'));
});

test('update workspace settings updates workspace', function () {
    $response = $this->actingAs($this->user)->put(route('workspace.settings.update'), [
        'name' => 'Updated Name',
        'timezone' => 'America/New_York',
    ]);

    $response->assertRedirect(route('workspace.settings'));

    $this->workspace->refresh();
    expect($this->workspace->name)->toBe('Updated Name');
    expect($this->workspace->timezone)->toBe('America/New_York');
});

test('update workspace settings validates required fields', function () {
    $response = $this->actingAs($this->user)->put(route('workspace.settings.update'), [
        'name' => '',
        'timezone' => '',
    ]);

    $response->assertSessionHasErrors(['name', 'timezone']);
});

test('update workspace settings validates timezone', function () {
    $response = $this->actingAs($this->user)->put(route('workspace.settings.update'), [
        'name' => 'Valid Name',
        'timezone' => 'Invalid/Timezone',
    ]);

    $response->assertSessionHasErrors('timezone');
});

// Destroy tests
test('destroy workspace requires authentication', function () {
    $response = $this->delete(route('workspaces.destroy', $this->workspace));

    $response->assertRedirect(route('login'));
});

test('destroy workspace deletes the workspace', function () {
    $workspaceId = $this->workspace->id;

    $response = $this->actingAs($this->user)->delete(route('workspaces.destroy', $this->workspace));

    $response->assertRedirect(route('workspaces.index'));
    expect(Workspace::find($workspaceId))->toBeNull();
});

test('destroy workspace clears current workspace if deleting current', function () {
    $this->actingAs($this->user)->delete(route('workspaces.destroy', $this->workspace));

    $this->user->refresh();
    expect($this->user->current_workspace_id)->toBeNull();
});

test('destroy workspace returns 403 for non-owner', function () {
    $otherUser = User::factory()->create(['setup' => Setup::Completed]);

    $response = $this->actingAs($otherUser)->delete(route('workspaces.destroy', $this->workspace));

    $response->assertForbidden();
});
