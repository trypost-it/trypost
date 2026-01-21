<?php

use App\Models\Language;
use App\Models\User;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->name)->toBe('Test User');
    expect($user->email)->toBe('test@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('user can update their language', function () {
    $user = User::factory()->create();
    $newLanguage = Language::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('posts.index'))
        ->patch(route('profile.language'), [
            'language_id' => $newLanguage->id,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('posts.index'));

    expect($user->refresh()->language_id)->toBe($newLanguage->id);
});

test('user cannot update language with invalid language id', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.language'), [
            'language_id' => '00000000-0000-0000-0000-000000000000',
        ]);

    $response->assertSessionHasErrors('language_id');
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete(route('profile.destroy'), [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home'));

    $this->assertGuest();
    expect($user->fresh())->toBeNull();
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect(route('profile.edit'));

    expect($user->fresh())->not->toBeNull();
});

test('deleting account updates members current_workspace_id when their workspace is deleted', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();

    // Create a workspace owned by the owner
    $workspace = \App\Models\Workspace::factory()->create(['user_id' => $owner->id]);
    $owner->workspaces()->attach($workspace->id, ['role' => \App\Enums\UserWorkspace\Role::Owner->value]);
    $owner->update(['current_workspace_id' => $workspace->id]);

    // Add member to the workspace and set it as their current
    $member->workspaces()->attach($workspace->id, ['role' => \App\Enums\UserWorkspace\Role::Member->value]);
    $member->update(['current_workspace_id' => $workspace->id]);

    // Verify setup
    expect($member->current_workspace_id)->toBe($workspace->id);

    // Owner deletes their account
    $this
        ->actingAs($owner)
        ->delete(route('profile.destroy'), [
            'password' => 'password',
        ]);

    // Verify member's current_workspace_id is updated to null (since they have no other workspace)
    expect($member->fresh()->current_workspace_id)->toBeNull();
});

test('deleting account updates members current_workspace_id to another workspace when available', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $otherOwner = User::factory()->create();

    // Create workspace owned by the owner being deleted
    $workspaceToDelete = \App\Models\Workspace::factory()->create(['user_id' => $owner->id]);
    $owner->workspaces()->attach($workspaceToDelete->id, ['role' => \App\Enums\UserWorkspace\Role::Owner->value]);
    $owner->update(['current_workspace_id' => $workspaceToDelete->id]);

    // Create another workspace owned by a different user
    $otherWorkspace = \App\Models\Workspace::factory()->create(['user_id' => $otherOwner->id]);
    $otherOwner->workspaces()->attach($otherWorkspace->id, ['role' => \App\Enums\UserWorkspace\Role::Owner->value]);

    // Add member to both workspaces
    $member->workspaces()->attach($workspaceToDelete->id, ['role' => \App\Enums\UserWorkspace\Role::Member->value]);
    $member->workspaces()->attach($otherWorkspace->id, ['role' => \App\Enums\UserWorkspace\Role::Member->value]);
    $member->update(['current_workspace_id' => $workspaceToDelete->id]);

    // Verify setup
    expect($member->current_workspace_id)->toBe($workspaceToDelete->id);

    // Owner deletes their account
    $this
        ->actingAs($owner)
        ->delete(route('profile.destroy'), [
            'password' => 'password',
        ]);

    // Verify member's current_workspace_id is updated to the other workspace
    expect($member->fresh()->current_workspace_id)->toBe($otherWorkspace->id);
});
