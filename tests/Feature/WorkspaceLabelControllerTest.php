<?php

use App\Enums\User\Setup;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceLabel;

beforeEach(function () {
    $this->user = User::factory()->create(['setup' => Setup::Completed]);
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);
});

// Index tests
test('labels index requires authentication', function () {
    $response = $this->get(route('labels.index'));

    $response->assertRedirect(route('login'));
});

test('labels index shows labels for workspace', function () {
    WorkspaceLabel::factory()->count(3)->create(['workspace_id' => $this->workspace->id]);

    $response = $this->actingAs($this->user)->get(route('labels.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('labels/Index', false)
        ->has('workspace')
        ->has('labels', 3)
    );
});

test('labels index redirects if no workspace', function () {
    $this->user->update(['current_workspace_id' => null]);

    $response = $this->actingAs($this->user)->get(route('labels.index'));

    $response->assertRedirect(route('workspaces.create'));
});

// Store tests
test('store label requires authentication', function () {
    $response = $this->post(route('labels.store'), [
        'name' => 'Test Label',
        'color' => '#FF5733',
    ]);

    $response->assertRedirect(route('login'));
});

test('store label creates label', function () {
    $response = $this->actingAs($this->user)->post(route('labels.store'), [
        'name' => 'New Label',
        'color' => '#FF5733',
    ]);

    $response->assertRedirect(route('labels.index'));

    $this->assertDatabaseHas('workspace_labels', [
        'workspace_id' => $this->workspace->id,
        'name' => 'New Label',
        'color' => '#FF5733',
    ]);
});

test('store label validates required fields', function () {
    $response = $this->actingAs($this->user)->post(route('labels.store'), [
        'name' => '',
        'color' => '',
    ]);

    $response->assertSessionHasErrors(['name', 'color']);
});

test('store label validates color format', function () {
    $response = $this->actingAs($this->user)->post(route('labels.store'), [
        'name' => 'Valid Name',
        'color' => 'invalid-color',
    ]);

    $response->assertSessionHasErrors('color');
});

// Update tests
test('update label requires authentication', function () {
    $label = WorkspaceLabel::factory()->create(['workspace_id' => $this->workspace->id]);

    $response = $this->put(route('labels.update', $label), [
        'name' => 'Updated Label',
        'color' => '#00FF00',
    ]);

    $response->assertRedirect(route('login'));
});

test('update label updates the label', function () {
    $label = WorkspaceLabel::factory()->create(['workspace_id' => $this->workspace->id]);

    $response = $this->actingAs($this->user)->put(route('labels.update', $label), [
        'name' => 'Updated Label',
        'color' => '#00FF00',
    ]);

    $response->assertRedirect(route('labels.index'));

    $label->refresh();
    expect($label->name)->toBe('Updated Label');
    expect($label->color)->toBe('#00FF00');
});

test('update label returns 404 for other workspace label', function () {
    $otherWorkspace = Workspace::factory()->create();
    $label = WorkspaceLabel::factory()->create(['workspace_id' => $otherWorkspace->id]);

    $response = $this->actingAs($this->user)->put(route('labels.update', $label), [
        'name' => 'Updated Label',
        'color' => '#00FF00',
    ]);

    $response->assertNotFound();
});

// Destroy tests
test('destroy label requires authentication', function () {
    $label = WorkspaceLabel::factory()->create(['workspace_id' => $this->workspace->id]);

    $response = $this->delete(route('labels.destroy', $label));

    $response->assertRedirect(route('login'));
});

test('destroy label deletes the label', function () {
    $label = WorkspaceLabel::factory()->create(['workspace_id' => $this->workspace->id]);

    $response = $this->actingAs($this->user)->delete(route('labels.destroy', $label));

    $response->assertRedirect(route('labels.index'));
    expect(WorkspaceLabel::find($label->id))->toBeNull();
});

test('destroy label returns 404 for other workspace label', function () {
    $otherWorkspace = Workspace::factory()->create();
    $label = WorkspaceLabel::factory()->create(['workspace_id' => $otherWorkspace->id]);

    $response = $this->actingAs($this->user)->delete(route('labels.destroy', $label));

    $response->assertNotFound();
});
