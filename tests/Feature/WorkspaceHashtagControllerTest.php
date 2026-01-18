<?php

use App\Enums\User\Setup;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceHashtag;

beforeEach(function () {
    $this->user = User::factory()->create(['setup' => Setup::Completed]);
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);
});

// Index tests
test('hashtags index requires authentication', function () {
    $response = $this->get(route('hashtags.index'));

    $response->assertRedirect(route('login'));
});

test('hashtags index shows hashtags for workspace', function () {
    WorkspaceHashtag::factory()->count(3)->create(['workspace_id' => $this->workspace->id]);

    $response = $this->actingAs($this->user)->get(route('hashtags.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('hashtags/Index', false)
        ->has('workspace')
        ->has('hashtags', 3)
    );
});

test('hashtags index redirects if no workspace', function () {
    $this->user->update(['current_workspace_id' => null]);

    $response = $this->actingAs($this->user)->get(route('hashtags.index'));

    $response->assertRedirect(route('workspaces.create'));
});

// Store tests
test('store hashtag requires authentication', function () {
    $response = $this->post(route('hashtags.store'), [
        'name' => 'Marketing',
        'hashtags' => '#marketing #digital #growth',
    ]);

    $response->assertRedirect(route('login'));
});

test('store hashtag creates hashtag group', function () {
    $response = $this->actingAs($this->user)->post(route('hashtags.store'), [
        'name' => 'Marketing',
        'hashtags' => '#marketing #digital #growth',
    ]);

    $response->assertRedirect(route('hashtags.index'));

    $this->assertDatabaseHas('workspace_hashtags', [
        'workspace_id' => $this->workspace->id,
        'name' => 'Marketing',
        'hashtags' => '#marketing #digital #growth',
    ]);
});

test('store hashtag validates required fields', function () {
    $response = $this->actingAs($this->user)->post(route('hashtags.store'), [
        'name' => '',
        'hashtags' => '',
    ]);

    $response->assertSessionHasErrors(['name', 'hashtags']);
});

// Update tests
test('update hashtag requires authentication', function () {
    $hashtag = WorkspaceHashtag::factory()->create(['workspace_id' => $this->workspace->id]);

    $response = $this->put(route('hashtags.update', $hashtag), [
        'name' => 'Updated Name',
        'hashtags' => '#updated #hashtags',
    ]);

    $response->assertRedirect(route('login'));
});

test('update hashtag updates the hashtag group', function () {
    $hashtag = WorkspaceHashtag::factory()->create(['workspace_id' => $this->workspace->id]);

    $response = $this->actingAs($this->user)->put(route('hashtags.update', $hashtag), [
        'name' => 'Updated Name',
        'hashtags' => '#updated #hashtags',
    ]);

    $response->assertRedirect(route('hashtags.index'));

    $hashtag->refresh();
    expect($hashtag->name)->toBe('Updated Name');
    expect($hashtag->hashtags)->toBe('#updated #hashtags');
});

test('update hashtag returns 404 for other workspace hashtag', function () {
    $otherWorkspace = Workspace::factory()->create();
    $hashtag = WorkspaceHashtag::factory()->create(['workspace_id' => $otherWorkspace->id]);

    $response = $this->actingAs($this->user)->put(route('hashtags.update', $hashtag), [
        'name' => 'Updated Name',
        'hashtags' => '#updated #hashtags',
    ]);

    $response->assertNotFound();
});

// Destroy tests
test('destroy hashtag requires authentication', function () {
    $hashtag = WorkspaceHashtag::factory()->create(['workspace_id' => $this->workspace->id]);

    $response = $this->delete(route('hashtags.destroy', $hashtag));

    $response->assertRedirect(route('login'));
});

test('destroy hashtag deletes the hashtag group', function () {
    $hashtag = WorkspaceHashtag::factory()->create(['workspace_id' => $this->workspace->id]);

    $response = $this->actingAs($this->user)->delete(route('hashtags.destroy', $hashtag));

    $response->assertRedirect(route('hashtags.index'));
    expect(WorkspaceHashtag::find($hashtag->id))->toBeNull();
});

test('destroy hashtag returns 404 for other workspace hashtag', function () {
    $otherWorkspace = Workspace::factory()->create();
    $hashtag = WorkspaceHashtag::factory()->create(['workspace_id' => $otherWorkspace->id]);

    $response = $this->actingAs($this->user)->delete(route('hashtags.destroy', $hashtag));

    $response->assertNotFound();
});
