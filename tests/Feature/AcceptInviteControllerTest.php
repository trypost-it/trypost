<?php

declare(strict_types=1);

use App\Enums\User\Setup;
use App\Models\Account;
use App\Models\Invite;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    $this->account = Account::factory()->create();
    $this->owner = User::factory()->create([
        'setup' => Setup::Completed,
        'account_id' => $this->account->id,
    ]);
    $this->account->update(['owner_id' => $this->owner->id]);
    $this->workspace = Workspace::factory()->create([
        'account_id' => $this->account->id,
        'user_id' => $this->owner->id,
    ]);
});

test('show invite displays invite details for guest', function () {
    $invite = Invite::factory()->create([
        'account_id' => $this->account->id,
        'invited_by' => $this->owner->id,
        'email' => 'newuser@example.com',
        'workspaces' => [$this->workspace->id],
    ]);

    $response = $this->get(route('app.invites.show', $invite));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('auth/AcceptInvite', false)
        ->has('invite')
        ->where('invite.id', $invite->id)
        ->where('invite.email', 'newuser@example.com')
        ->where('invite.account.name', $this->account->name)
    );
});

test('show invite displays invite details for authenticated user', function () {
    $user = User::factory()->create([
        'email' => 'invitee@example.com',
        'setup' => Setup::Completed,
    ]);

    $invite = Invite::factory()->create([
        'account_id' => $this->account->id,
        'invited_by' => $this->owner->id,
        'email' => 'invitee@example.com',
        'workspaces' => [$this->workspace->id],
    ]);

    $response = $this->actingAs($user)->get(route('app.invites.show', $invite));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('auth/AcceptInvite', false)
        ->has('invite')
    );
});

test('show invite returns 404 for non-existent invite', function () {
    $response = $this->get(route('app.invites.show', 'non-existent-uuid'));

    $response->assertNotFound();
});

test('accept invite requires authentication', function () {
    $invite = Invite::factory()->create([
        'account_id' => $this->account->id,
        'invited_by' => $this->owner->id,
    ]);

    $response = $this->post(route('app.invites.accept', $invite));

    $response->assertRedirect(route('login'));
});

test('accept invite adds user to account and workspaces', function () {
    $user = User::factory()->create([
        'email' => 'invitee@example.com',
        'setup' => Setup::Completed,
    ]);

    $invite = Invite::factory()->create([
        'account_id' => $this->account->id,
        'invited_by' => $this->owner->id,
        'email' => 'invitee@example.com',
        'workspaces' => [$this->workspace->id],
    ]);

    $response = $this->actingAs($user)->post(route('app.invites.accept', $invite));

    $response->assertRedirect(route('app.calendar'));

    // User should be added to the account
    $user->refresh();
    expect($user->account_id)->toBe($this->account->id);

    // User should be member of workspace
    expect($this->workspace->members()->where('user_id', $user->id)->exists())->toBeTrue();

    // User's current workspace should be updated
    expect($user->current_workspace_id)->toBe($this->workspace->id);

    // Invite should be marked as accepted
    $invite->refresh();
    expect($invite->accepted_at)->not->toBeNull();
});

test('accept invite fails for wrong email', function () {
    $user = User::factory()->create([
        'email' => 'different@example.com',
        'setup' => Setup::Completed,
    ]);

    $invite = Invite::factory()->create([
        'account_id' => $this->account->id,
        'invited_by' => $this->owner->id,
        'email' => 'invitee@example.com',
        'workspaces' => [$this->workspace->id],
    ]);

    $response = $this->actingAs($user)->post(route('app.invites.accept', $invite));

    $response->assertRedirect(route('app.calendar'));
    $response->assertSessionHas('flash.bannerStyle', 'danger');

    // Invite should NOT be accepted
    $invite->refresh();
    expect($invite->accepted_at)->toBeNull();
});

test('accept invite handles already member of account', function () {
    $user = User::factory()->create([
        'email' => 'invitee@example.com',
        'setup' => Setup::Completed,
        'account_id' => $this->account->id,
    ]);

    $invite = Invite::factory()->create([
        'account_id' => $this->account->id,
        'invited_by' => $this->owner->id,
        'email' => 'invitee@example.com',
        'workspaces' => [$this->workspace->id],
    ]);

    $response = $this->actingAs($user)->post(route('app.invites.accept', $invite));

    $response->assertRedirect(route('app.calendar'));
    $response->assertSessionHas('flash.bannerStyle', 'info');

    // Invite should be marked as accepted
    $invite->refresh();
    expect($invite->accepted_at)->not->toBeNull();
});

test('decline invite requires authentication', function () {
    $invite = Invite::factory()->create([
        'account_id' => $this->account->id,
        'invited_by' => $this->owner->id,
    ]);

    $response = $this->post(route('app.invites.decline', $invite));

    $response->assertRedirect(route('login'));
});

test('decline invite deletes the invite', function () {
    $user = User::factory()->create([
        'email' => 'invitee@example.com',
        'setup' => Setup::Completed,
    ]);

    $invite = Invite::factory()->create([
        'account_id' => $this->account->id,
        'invited_by' => $this->owner->id,
        'email' => 'invitee@example.com',
        'workspaces' => [$this->workspace->id],
    ]);

    $response = $this->actingAs($user)->post(route('app.invites.decline', $invite));

    $response->assertRedirect(route('app.calendar'));
    $response->assertSessionHas('flash.bannerStyle', 'info');

    // Invite should be deleted
    expect(Invite::find($invite->id))->toBeNull();
});

test('decline invite fails for wrong email', function () {
    $user = User::factory()->create([
        'email' => 'different@example.com',
        'setup' => Setup::Completed,
    ]);

    $invite = Invite::factory()->create([
        'account_id' => $this->account->id,
        'invited_by' => $this->owner->id,
        'email' => 'invitee@example.com',
        'workspaces' => [$this->workspace->id],
    ]);

    $response = $this->actingAs($user)->post(route('app.invites.decline', $invite));

    $response->assertRedirect(route('app.calendar'));
    $response->assertSessionHas('flash.bannerStyle', 'danger');

    // Invite should NOT be deleted
    expect(Invite::find($invite->id))->not->toBeNull();
});
