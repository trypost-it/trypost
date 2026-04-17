<?php

declare(strict_types=1);

use App\Enums\UserWorkspace\Role as WorkspaceRole;
use App\Mail\WorkspaceInvite as WorkspaceInviteMail;
use App\Models\Account;
use App\Models\Invite;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    config(['trypost.self_hosted' => true]);

    $this->account = Account::factory()->create();
    $this->user = User::factory()->create([
        'account_id' => $this->account->id,
    ]);
    $this->account->update(['owner_id' => $this->user->id]);
    $this->workspace = Workspace::factory()->create([
        'user_id' => $this->user->id,
        'account_id' => $this->account->id,
    ]);
    $this->workspace->members()->attach($this->user->id, ['role' => WorkspaceRole::Admin->value]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);
});

// Index tests
test('members index requires authentication', function () {
    $response = $this->get(route('app.members'));

    $response->assertRedirect(route('login'));
});

test('workspace settings shows members and invites', function () {
    Invite::factory()->create([
        'account_id' => $this->account->id,
        'invited_by' => $this->user->id,
        'workspaces' => [$this->workspace->id],
    ]);

    $response = $this->actingAs($this->user)->get(route('app.workspace.settings'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('settings/Workspace', false)
        ->has('workspace')
        ->has('members')
        ->has('invitations')
    );
});

// Store invite tests
test('store invite requires authentication', function () {
    $response = $this->post(route('app.invites.store'), [
        'email' => 'test@example.com',
        'role' => WorkspaceRole::Member->value,
    ]);

    $response->assertRedirect(route('login'));
});

test('store invite creates invite and sends email', function () {
    $response = $this->actingAs($this->user)->post(route('app.invites.store'), [
        'email' => 'newmember@example.com',
        'role' => WorkspaceRole::Member->value,
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('invites', [
        'account_id' => $this->account->id,
        'email' => 'newmember@example.com',
    ]);

    Mail::assertQueued(WorkspaceInviteMail::class);
});

test('store invite fails if invite already exists', function () {
    Invite::factory()->create([
        'account_id' => $this->account->id,
        'invited_by' => $this->user->id,
        'email' => 'existing@example.com',
        'workspaces' => [$this->workspace->id],
    ]);

    $response = $this->actingAs($this->user)->post(route('app.invites.store'), [
        'email' => 'existing@example.com',
        'role' => WorkspaceRole::Member->value,
    ]);

    $response->assertSessionHasErrors('email');
});

test('store invite fails if user is already member', function () {
    $member = User::factory()->create([
        'account_id' => $this->account->id,
    ]);
    $this->workspace->members()->attach($member->id, ['role' => WorkspaceRole::Member->value]);

    $response = $this->actingAs($this->user)->post(route('app.invites.store'), [
        'email' => $member->email,
        'role' => WorkspaceRole::Member->value,
    ]);

    $response->assertSessionHasErrors('email');
});

// Destroy invite tests
test('destroy invite requires authentication', function () {
    $invite = Invite::factory()->create([
        'account_id' => $this->account->id,
        'invited_by' => $this->user->id,
        'workspaces' => [$this->workspace->id],
    ]);

    $response = $this->delete(route('app.invites.destroy', $invite));

    $response->assertRedirect(route('login'));
});

test('destroy invite deletes invite', function () {
    $invite = Invite::factory()->create([
        'account_id' => $this->account->id,
        'invited_by' => $this->user->id,
        'workspaces' => [$this->workspace->id],
    ]);

    $response = $this->actingAs($this->user)->delete(route('app.invites.destroy', $invite));

    $response->assertRedirect();
    expect(Invite::find($invite->id))->toBeNull();
});

test('destroy invite returns 404 for other account invite', function () {
    $otherAccount = Account::factory()->create();
    $invite = Invite::factory()->create([
        'account_id' => $otherAccount->id,
        'workspaces' => [],
    ]);

    $response = $this->actingAs($this->user)->delete(route('app.invites.destroy', $invite));

    $response->assertNotFound();
});

// Remove member tests
test('remove member requires authentication', function () {
    $member = User::factory()->create([
        'account_id' => $this->account->id,
    ]);
    $this->workspace->members()->attach($member->id, ['role' => WorkspaceRole::Member->value]);

    $response = $this->delete(route('app.members.remove', $member));

    $response->assertRedirect(route('login'));
});

test('remove member removes user from workspace', function () {
    $member = User::factory()->create([
        'account_id' => $this->account->id,
    ]);
    $this->workspace->members()->attach($member->id, ['role' => WorkspaceRole::Member->value]);

    $response = $this->actingAs($this->user)->delete(route('app.members.remove', $member));

    $response->assertRedirect();
    expect($this->workspace->hasMember($member))->toBeFalse();
});

test('remove member fails for owner', function () {
    $response = $this->actingAs($this->user)->delete(route('app.members.remove', $this->user));

    $response->assertSessionHasErrors('member');
});

// Update role tests
test('update role requires authentication', function () {
    $member = User::factory()->create([
        'account_id' => $this->account->id,
    ]);
    $this->workspace->members()->attach($member->id, ['role' => WorkspaceRole::Member->value]);

    $response = $this->put(route('app.members.update-role', $member), [
        'role' => WorkspaceRole::Admin->value,
    ]);

    $response->assertRedirect(route('login'));
});

test('update role changes member to admin', function () {
    $member = User::factory()->create([
        'account_id' => $this->account->id,
    ]);
    $this->workspace->members()->attach($member->id, ['role' => WorkspaceRole::Member->value]);

    $response = $this->actingAs($this->user)->put(route('app.members.update-role', $member), [
        'role' => WorkspaceRole::Admin->value,
    ]);

    $response->assertRedirect();
    expect($this->workspace->members()->where('user_id', $member->id)->first()->pivot->role)->toBe(WorkspaceRole::Admin->value);
});

test('update role changes admin to member', function () {
    $member = User::factory()->create([
        'account_id' => $this->account->id,
    ]);
    $this->workspace->members()->attach($member->id, ['role' => WorkspaceRole::Admin->value]);

    $response = $this->actingAs($this->user)->put(route('app.members.update-role', $member), [
        'role' => WorkspaceRole::Member->value,
    ]);

    $response->assertRedirect();
    expect($this->workspace->members()->where('user_id', $member->id)->first()->pivot->role)->toBe(WorkspaceRole::Member->value);
});

test('update role fails for workspace owner', function () {
    $response = $this->actingAs($this->user)->put(route('app.members.update-role', $this->user), [
        'role' => WorkspaceRole::Member->value,
    ]);

    $response->assertSessionHasErrors('role');
});

test('update role fails with invalid role', function () {
    $member = User::factory()->create([
        'account_id' => $this->account->id,
    ]);
    $this->workspace->members()->attach($member->id, ['role' => WorkspaceRole::Member->value]);

    $response = $this->actingAs($this->user)->put(route('app.members.update-role', $member), [
        'role' => 'invalid',
    ]);

    $response->assertSessionHasErrors('role');
});

test('update role requires authorization', function () {
    $member = User::factory()->create([
        'account_id' => $this->account->id,
    ]);
    $this->workspace->members()->attach($member->id, ['role' => WorkspaceRole::Member->value]);

    $nonAdmin = User::factory()->create([
        'account_id' => $this->account->id,
    ]);
    $this->workspace->members()->attach($nonAdmin->id, ['role' => WorkspaceRole::Member->value]);
    $nonAdmin->update(['current_workspace_id' => $this->workspace->id]);

    $response = $this->actingAs($nonAdmin)->put(route('app.members.update-role', $member), [
        'role' => WorkspaceRole::Admin->value,
    ]);

    $response->assertForbidden();
});

test('store invite validates email is required', function () {
    $response = $this->actingAs($this->user)->post(route('app.invites.store'), []);

    $response->assertSessionHasErrors('email');
});

test('store invite validates email format', function () {
    $response = $this->actingAs($this->user)->post(route('app.invites.store'), [
        'email' => 'not-an-email',
    ]);

    $response->assertSessionHasErrors('email');
});

test('store invite validates role must be valid', function () {
    $response = $this->actingAs($this->user)->post(route('app.invites.store'), [
        'email' => 'test@example.com',
        'role' => 'owner',
    ]);

    $response->assertSessionHasErrors('role');
});
