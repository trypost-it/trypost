<?php

use App\Enums\User\Setup;
use App\Enums\UserWorkspace\Role as WorkspaceRole;
use App\Mail\WorkspaceInvite as WorkspaceInviteMail;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvite;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->user = User::factory()->create(['setup' => Setup::Completed]);
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);
});

// Index tests
test('members index requires authentication', function () {
    $response = $this->get(route('members'));

    $response->assertRedirect(route('login'));
});

test('members index shows members and invites', function () {
    $invite = WorkspaceInvite::factory()->create([
        'workspace_id' => $this->workspace->id,
        'invited_by' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)->get(route('members'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('settings/Members', false)
        ->has('workspace')
        ->has('invites')
        ->has('members')
        ->has('owner')
        ->has('roles')
    );
});

test('members index redirects if no workspace', function () {
    $this->user->update(['current_workspace_id' => null]);

    $response = $this->actingAs($this->user)->get(route('members'));

    $response->assertRedirect(route('workspaces.create'));
});

// Store invite tests
test('store invite requires authentication', function () {
    $response = $this->post(route('invites.store'), [
        'email' => 'test@example.com',
        'role' => WorkspaceRole::Member->value,
    ]);

    $response->assertRedirect(route('login'));
});

test('store invite creates invite and sends email', function () {
    $response = $this->actingAs($this->user)->post(route('invites.store'), [
        'email' => 'newmember@example.com',
        'role' => WorkspaceRole::Member->value,
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('workspace_invites', [
        'workspace_id' => $this->workspace->id,
        'email' => 'newmember@example.com',
    ]);

    Mail::assertQueued(WorkspaceInviteMail::class);
});

test('store invite fails if invite already exists', function () {
    WorkspaceInvite::factory()->create([
        'workspace_id' => $this->workspace->id,
        'invited_by' => $this->user->id,
        'email' => 'existing@example.com',
    ]);

    $response = $this->actingAs($this->user)->post(route('invites.store'), [
        'email' => 'existing@example.com',
        'role' => WorkspaceRole::Member->value,
    ]);

    $response->assertSessionHasErrors('email');
});

test('store invite fails if user is already member', function () {
    $member = User::factory()->create(['setup' => Setup::Completed]);
    $this->workspace->members()->attach($member->id, ['role' => WorkspaceRole::Member->value]);

    $response = $this->actingAs($this->user)->post(route('invites.store'), [
        'email' => $member->email,
        'role' => WorkspaceRole::Member->value,
    ]);

    $response->assertSessionHasErrors('email');
});

// Destroy invite tests
test('destroy invite requires authentication', function () {
    $invite = WorkspaceInvite::factory()->create([
        'workspace_id' => $this->workspace->id,
        'invited_by' => $this->user->id,
    ]);

    $response = $this->delete(route('invites.destroy', $invite));

    $response->assertRedirect(route('login'));
});

test('destroy invite deletes invite', function () {
    $invite = WorkspaceInvite::factory()->create([
        'workspace_id' => $this->workspace->id,
        'invited_by' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)->delete(route('invites.destroy', $invite));

    $response->assertRedirect();
    expect(WorkspaceInvite::find($invite->id))->toBeNull();
});

test('destroy invite returns 404 for other workspace invite', function () {
    $otherWorkspace = Workspace::factory()->create();
    $invite = WorkspaceInvite::factory()->create([
        'workspace_id' => $otherWorkspace->id,
    ]);

    $response = $this->actingAs($this->user)->delete(route('invites.destroy', $invite));

    $response->assertNotFound();
});

// Show invite tests
test('show invite displays invite details', function () {
    $invite = WorkspaceInvite::factory()->create([
        'workspace_id' => $this->workspace->id,
        'invited_by' => $this->user->id,
    ]);

    $response = $this->get(route('invites.show', $invite->token));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('invites/Accept', false)
        ->has('invite')
    );
});

test('show invite redirects for accepted invite', function () {
    $invite = WorkspaceInvite::factory()->accepted()->create([
        'workspace_id' => $this->workspace->id,
        'invited_by' => $this->user->id,
    ]);

    $response = $this->get(route('invites.show', $invite->token));

    $response->assertRedirect(route('login'));
});

// Accept invite tests
test('accept invite redirects to login if not authenticated', function () {
    $invite = WorkspaceInvite::factory()->create([
        'workspace_id' => $this->workspace->id,
        'invited_by' => $this->user->id,
    ]);

    $response = $this->post(route('invites.accept', $invite->token));

    $response->assertRedirect(route('login'));
});

test('accept invite adds user to workspace', function () {
    $invite = WorkspaceInvite::factory()->create([
        'workspace_id' => $this->workspace->id,
        'invited_by' => $this->user->id,
    ]);

    $newUser = User::factory()->create(['setup' => Setup::Completed]);

    $response = $this->actingAs($newUser)->post(route('invites.accept', $invite->token));

    $response->assertRedirect(route('calendar'));
    expect($this->workspace->hasMember($newUser))->toBeTrue();
});

test('accept invite redirects for accepted invite', function () {
    $invite = WorkspaceInvite::factory()->accepted()->create([
        'workspace_id' => $this->workspace->id,
        'invited_by' => $this->user->id,
    ]);

    $newUser = User::factory()->create(['setup' => Setup::Completed]);

    $response = $this->actingAs($newUser)->post(route('invites.accept', $invite->token));

    $response->assertRedirect(route('login'));
});

test('accept invite handles already member', function () {
    $invite = WorkspaceInvite::factory()->create([
        'workspace_id' => $this->workspace->id,
        'invited_by' => $this->user->id,
    ]);

    $member = User::factory()->create(['setup' => Setup::Completed]);
    $this->workspace->members()->attach($member->id, ['role' => WorkspaceRole::Member->value]);

    $response = $this->actingAs($member)->post(route('invites.accept', $invite->token));

    $response->assertRedirect(route('calendar'));
    $response->assertSessionHas('flash.banner', 'You are already a member of this workspace.');
});

// Remove member tests
test('remove member requires authentication', function () {
    $member = User::factory()->create(['setup' => Setup::Completed]);
    $this->workspace->members()->attach($member->id, ['role' => WorkspaceRole::Member->value]);

    $response = $this->delete(route('members.remove', $member));

    $response->assertRedirect(route('login'));
});

test('remove member removes user from workspace', function () {
    $member = User::factory()->create(['setup' => Setup::Completed]);
    $this->workspace->members()->attach($member->id, ['role' => WorkspaceRole::Member->value]);

    $response = $this->actingAs($this->user)->delete(route('members.remove', $member));

    $response->assertRedirect();
    expect($this->workspace->hasMember($member))->toBeFalse();
});

test('remove member fails for owner', function () {
    $response = $this->actingAs($this->user)->delete(route('members.remove', $this->user));

    $response->assertSessionHasErrors('member');
});
