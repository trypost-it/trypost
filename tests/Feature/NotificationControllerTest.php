<?php

declare(strict_types=1);

use App\Enums\User\Setup;
use App\Enums\UserWorkspace\Role;
use App\Models\Notification;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    $this->user = User::factory()->create(['setup' => Setup::Completed]);
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Member->value]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);
});

test('notifications index requires authentication', function () {
    $response = $this->getJson(route('app.notifications.index'));
    $response->assertUnauthorized();
});

test('notifications index returns notifications and unread count', function () {
    Notification::factory()->count(3)->create([
        'user_id' => $this->user->id,
        'workspace_id' => $this->workspace->id,
    ]);

    Notification::factory()->read()->create([
        'user_id' => $this->user->id,
        'workspace_id' => $this->workspace->id,
    ]);

    $response = $this->actingAs($this->user)->getJson(route('app.notifications.index'));

    $response->assertOk();
    $response->assertJsonCount(4, 'notifications');
    $response->assertJsonPath('unread_count', 3);
});

test('notifications index excludes archived notifications', function () {
    Notification::factory()->create([
        'user_id' => $this->user->id,
        'workspace_id' => $this->workspace->id,
    ]);

    Notification::factory()->create([
        'user_id' => $this->user->id,
        'workspace_id' => $this->workspace->id,
        'archived_at' => now(),
    ]);

    $response = $this->actingAs($this->user)->getJson(route('app.notifications.index'));

    $response->assertOk();
    $response->assertJsonCount(1, 'notifications');
});

test('notifications index only shows current workspace notifications', function () {
    Notification::factory()->create([
        'user_id' => $this->user->id,
        'workspace_id' => $this->workspace->id,
    ]);

    $otherWorkspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    Notification::factory()->create([
        'user_id' => $this->user->id,
        'workspace_id' => $otherWorkspace->id,
    ]);

    $response = $this->actingAs($this->user)->getJson(route('app.notifications.index'));

    $response->assertOk();
    $response->assertJsonCount(1, 'notifications');
});

test('mark notification as read', function () {
    $notification = Notification::factory()->create([
        'user_id' => $this->user->id,
        'workspace_id' => $this->workspace->id,
    ]);

    $response = $this->actingAs($this->user)->putJson(route('app.notifications.read', $notification));

    $response->assertOk();
    expect($notification->fresh()->read_at)->not->toBeNull();
});

test('cannot mark another users notification as read', function () {
    $otherUser = User::factory()->create();
    $notification = Notification::factory()->create([
        'user_id' => $otherUser->id,
        'workspace_id' => $this->workspace->id,
    ]);

    $response = $this->actingAs($this->user)->putJson(route('app.notifications.read', $notification));

    $response->assertForbidden();
});

test('mark all as read', function () {
    Notification::factory()->count(3)->create([
        'user_id' => $this->user->id,
        'workspace_id' => $this->workspace->id,
    ]);

    $response = $this->actingAs($this->user)->postJson(route('app.notifications.read-all'));

    $response->assertOk();

    $unread = Notification::where('user_id', $this->user->id)
        ->whereNull('read_at')
        ->count();

    expect($unread)->toBe(0);
});

test('archive all notifications', function () {
    Notification::factory()->count(3)->create([
        'user_id' => $this->user->id,
        'workspace_id' => $this->workspace->id,
    ]);

    $response = $this->actingAs($this->user)->postJson(route('app.notifications.archive-all'));

    $response->assertOk();

    $active = Notification::where('user_id', $this->user->id)
        ->whereNull('archived_at')
        ->count();

    expect($active)->toBe(0);
});

test('mark as read requires authentication', function () {
    $notification = Notification::factory()->create([
        'user_id' => $this->user->id,
        'workspace_id' => $this->workspace->id,
    ]);

    $this->putJson(route('app.notifications.read', $notification))->assertUnauthorized();
});

test('mark all as read requires authentication', function () {
    $this->postJson(route('app.notifications.read-all'))->assertUnauthorized();
});

test('archive all requires authentication', function () {
    $this->postJson(route('app.notifications.archive-all'))->assertUnauthorized();
});
