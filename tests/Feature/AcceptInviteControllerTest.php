<?php

use App\Enums\User\Setup;
use App\Enums\UserWorkspace\Role as WorkspaceRole;
use App\Models\Language;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvite;

beforeEach(function () {
    Language::factory()->create(['code' => 'en-US']);
    $this->owner = User::factory()->create(['setup' => Setup::Completed]);
    $this->workspace = Workspace::factory()->create(['user_id' => $this->owner->id]);
});

test('show invite displays invite details for guest', function () {
    $invite = WorkspaceInvite::factory()->create([
        'workspace_id' => $this->workspace->id,
        'email' => 'newuser@example.com',
        'role' => WorkspaceRole::Member,
    ]);

    $response = $this->get(route('invites.show', $invite));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('auth/AcceptInvite', false)
        ->has('invite')
        ->where('invite.id', $invite->id)
        ->where('invite.email', 'newuser@example.com')
        ->where('invite.role.value', WorkspaceRole::Member->value)
        ->where('invite.workspace.name', $this->workspace->name)
    );
});

test('show invite displays invite details for authenticated user', function () {
    $user = User::factory()->create([
        'email' => 'invitee@example.com',
        'setup' => Setup::Completed,
    ]);

    $invite = WorkspaceInvite::factory()->create([
        'workspace_id' => $this->workspace->id,
        'email' => 'invitee@example.com',
        'role' => WorkspaceRole::Member,
    ]);

    $response = $this->actingAs($user)->get(route('invites.show', $invite));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('auth/AcceptInvite', false)
        ->has('invite')
    );
});

test('show invite returns 404 for non-existent invite', function () {
    $response = $this->get(route('invites.show', 'non-existent-uuid'));

    $response->assertNotFound();
});

test('accept invite requires authentication', function () {
    $invite = WorkspaceInvite::factory()->create([
        'workspace_id' => $this->workspace->id,
    ]);

    $response = $this->post(route('invites.accept', $invite));

    $response->assertRedirect(route('login'));
});

test('accept invite adds user to workspace', function () {
    $user = User::factory()->create([
        'email' => 'invitee@example.com',
        'setup' => Setup::Completed,
    ]);

    $invite = WorkspaceInvite::factory()->create([
        'workspace_id' => $this->workspace->id,
        'email' => 'invitee@example.com',
        'role' => WorkspaceRole::Admin,
    ]);

    $response = $this->actingAs($user)->post(route('invites.accept', $invite));

    $response->assertRedirect(route('calendar'));

    // User should be member of workspace
    expect($this->workspace->hasMember($user))->toBeTrue();

    // Invite should be deleted
    expect(WorkspaceInvite::find($invite->id))->toBeNull();

    // User's current workspace should be updated
    $user->refresh();
    expect($user->current_workspace_id)->toBe($this->workspace->id);
});

test('accept invite fails for wrong email', function () {
    $user = User::factory()->create([
        'email' => 'different@example.com',
        'setup' => Setup::Completed,
    ]);

    $invite = WorkspaceInvite::factory()->create([
        'workspace_id' => $this->workspace->id,
        'email' => 'invitee@example.com',
        'role' => WorkspaceRole::Member,
    ]);

    $response = $this->actingAs($user)->post(route('invites.accept', $invite));

    $response->assertRedirect(route('calendar'));
    $response->assertSessionHas('flash.bannerStyle', 'danger');

    // Invite should NOT be deleted
    expect(WorkspaceInvite::find($invite->id))->not->toBeNull();
});

test('accept invite handles already member', function () {
    $user = User::factory()->create([
        'email' => 'invitee@example.com',
        'setup' => Setup::Completed,
    ]);

    $this->workspace->members()->attach($user->id, ['role' => WorkspaceRole::Member->value]);

    $invite = WorkspaceInvite::factory()->create([
        'workspace_id' => $this->workspace->id,
        'email' => 'invitee@example.com',
        'role' => WorkspaceRole::Admin,
    ]);

    $response = $this->actingAs($user)->post(route('invites.accept', $invite));

    $response->assertRedirect(route('calendar'));
    $response->assertSessionHas('flash.bannerStyle', 'info');

    // Invite should be deleted
    expect(WorkspaceInvite::find($invite->id))->toBeNull();
});

test('decline invite requires authentication', function () {
    $invite = WorkspaceInvite::factory()->create([
        'workspace_id' => $this->workspace->id,
    ]);

    $response = $this->post(route('invites.decline', $invite));

    $response->assertRedirect(route('login'));
});

test('decline invite deletes the invite', function () {
    $user = User::factory()->create([
        'email' => 'invitee@example.com',
        'setup' => Setup::Completed,
    ]);

    $invite = WorkspaceInvite::factory()->create([
        'workspace_id' => $this->workspace->id,
        'email' => 'invitee@example.com',
        'role' => WorkspaceRole::Member,
    ]);

    $response = $this->actingAs($user)->post(route('invites.decline', $invite));

    $response->assertRedirect(route('calendar'));
    $response->assertSessionHas('flash.bannerStyle', 'info');

    // Invite should be deleted
    expect(WorkspaceInvite::find($invite->id))->toBeNull();

    // User should NOT be member of workspace
    expect($this->workspace->hasMember($user))->toBeFalse();
});

test('decline invite fails for wrong email', function () {
    $user = User::factory()->create([
        'email' => 'different@example.com',
        'setup' => Setup::Completed,
    ]);

    $invite = WorkspaceInvite::factory()->create([
        'workspace_id' => $this->workspace->id,
        'email' => 'invitee@example.com',
        'role' => WorkspaceRole::Member,
    ]);

    $response = $this->actingAs($user)->post(route('invites.decline', $invite));

    $response->assertRedirect(route('calendar'));
    $response->assertSessionHas('flash.bannerStyle', 'danger');

    // Invite should NOT be deleted
    expect(WorkspaceInvite::find($invite->id))->not->toBeNull();
});
