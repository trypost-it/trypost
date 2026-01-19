<?php

use App\Models\User;
use App\Models\Workspace;

test('user can get owned workspaces', function () {
    $user = User::factory()->create();
    $workspace1 = Workspace::factory()->create(['user_id' => $user->id]);
    $workspace2 = Workspace::factory()->create(['user_id' => $user->id]);

    expect($user->workspaces)->toHaveCount(2);
    expect($user->workspaces->pluck('id')->toArray())->toContain($workspace1->id, $workspace2->id);
});

test('user can get member workspaces', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $owner->id]);
    $workspace->members()->attach($member->id, ['role' => 'member']);

    expect($member->memberWorkspaces)->toHaveCount(1);
    expect($member->memberWorkspaces->first()->id)->toBe($workspace->id);
});

test('user can get current workspace', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $user->id]);
    $user->update(['current_workspace_id' => $workspace->id]);

    expect($user->currentWorkspace->id)->toBe($workspace->id);
});

test('user can switch workspace', function () {
    $user = User::factory()->create();
    $workspace1 = Workspace::factory()->create(['user_id' => $user->id]);
    $workspace2 = Workspace::factory()->create(['user_id' => $user->id]);
    $user->update(['current_workspace_id' => $workspace1->id]);

    $user->switchWorkspace($workspace2);

    expect($user->fresh()->current_workspace_id)->toBe($workspace2->id);
});

test('user belongs to owned workspace', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $user->id]);

    expect($user->belongsToWorkspace($workspace))->toBeTrue();
});

test('user belongs to member workspace', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $owner->id]);
    $workspace->members()->attach($member->id, ['role' => 'member']);

    expect($member->belongsToWorkspace($workspace))->toBeTrue();
});

test('user does not belong to other workspace', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $otherUser->id]);

    expect($user->belongsToWorkspace($workspace))->toBeFalse();
});

test('user can get owned workspaces count', function () {
    $user = User::factory()->create();
    Workspace::factory()->count(3)->create(['user_id' => $user->id]);

    expect($user->ownedWorkspacesCount())->toBe(3);
});

test('user without subscription can create first workspace', function () {
    $user = User::factory()->create();

    expect($user->canCreateWorkspace())->toBeTrue();
});

test('user without subscription cannot create second workspace', function () {
    $user = User::factory()->create();
    Workspace::factory()->create(['user_id' => $user->id]);

    expect($user->canCreateWorkspace())->toBeFalse();
});

test('user with subscription can create workspaces up to quantity', function () {
    $user = User::factory()->create();
    $user->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_123',
        'stripe_status' => 'active',
        'stripe_price' => 'price_123',
        'quantity' => 3,
    ]);

    Workspace::factory()->create(['user_id' => $user->id]);

    expect($user->canCreateWorkspace())->toBeTrue();
});

test('user with subscription cannot exceed workspace quantity', function () {
    $user = User::factory()->create();
    $user->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_123',
        'stripe_status' => 'active',
        'stripe_price' => 'price_123',
        'quantity' => 2,
    ]);

    Workspace::factory()->count(2)->create(['user_id' => $user->id]);

    expect($user->canCreateWorkspace())->toBeFalse();
});

test('increment workspace quantity does nothing without subscription', function () {
    $user = User::factory()->create();

    $user->incrementWorkspaceQuantity();

    expect($user->subscription('default'))->toBeNull();
});

test('decrement workspace quantity does nothing without subscription', function () {
    $user = User::factory()->create();

    $user->decrementWorkspaceQuantity();

    expect($user->subscription('default'))->toBeNull();
});

test('sync workspace quantity does nothing without subscription', function () {
    $user = User::factory()->create();
    Workspace::factory()->count(2)->create(['user_id' => $user->id]);

    $user->syncWorkspaceQuantity();

    expect($user->subscription('default'))->toBeNull();
});

test('sync workspace quantity does nothing with zero workspaces', function () {
    $user = User::factory()->create();
    $user->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_123',
        'stripe_status' => 'active',
        'stripe_price' => 'price_123',
        'quantity' => 5,
    ]);

    $user->syncWorkspaceQuantity();

    // Quantity remains unchanged because there are no workspaces
    expect($user->subscription('default')->quantity)->toBe(5);
});
