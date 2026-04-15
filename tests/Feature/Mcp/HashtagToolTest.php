<?php

declare(strict_types=1);

use App\Enums\UserWorkspace\Role;
use App\Mcp\Servers\TryPostServer;
use App\Mcp\Tools\Hashtag\CreateHashtagTool;
use App\Mcp\Tools\Hashtag\DeleteHashtagTool;
use App\Mcp\Tools\Hashtag\ListHashtagsTool;
use App\Mcp\Tools\Hashtag\UpdateHashtagTool;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceHashtag;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Member->value]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);
});

test('can list hashtags', function () {
    WorkspaceHashtag::factory()->count(2)->create(['workspace_id' => $this->workspace->id]);

    $response = TryPostServer::actingAs($this->user)
        ->tool(ListHashtagsTool::class, []);

    $response->assertOk();
});

test('can create hashtag', function () {
    $response = TryPostServer::actingAs($this->user)
        ->tool(CreateHashtagTool::class, [
            'name' => 'Marketing',
            'hashtags' => '#marketing #social',
        ]);

    $response->assertOk();
    $response->assertSee('Marketing');
    expect($this->workspace->hashtags()->count())->toBe(1);
});

test('create hashtag validates required fields', function () {
    $response = TryPostServer::actingAs($this->user)
        ->tool(CreateHashtagTool::class, []);

    $response->assertHasErrors();
});

test('can update hashtag', function () {
    $hashtag = WorkspaceHashtag::factory()->create(['workspace_id' => $this->workspace->id]);

    $response = TryPostServer::actingAs($this->user)
        ->tool(UpdateHashtagTool::class, [
            'hashtag_id' => $hashtag->id,
            'name' => 'Updated',
            'hashtags' => '#updated',
        ]);

    $response->assertOk();
    $response->assertSee('Updated');
});

test('cannot update hashtag from another workspace', function () {
    $otherWorkspace = Workspace::factory()->create();
    $hashtag = WorkspaceHashtag::factory()->create(['workspace_id' => $otherWorkspace->id]);

    $response = TryPostServer::actingAs($this->user)
        ->tool(UpdateHashtagTool::class, [
            'hashtag_id' => $hashtag->id,
            'name' => 'Hacked',
            'hashtags' => '#hacked',
        ]);

    $response->assertHasErrors(['Hashtag not found.']);
});

test('can delete hashtag', function () {
    $hashtag = WorkspaceHashtag::factory()->create(['workspace_id' => $this->workspace->id]);

    $response = TryPostServer::actingAs($this->user)
        ->tool(DeleteHashtagTool::class, ['hashtag_id' => $hashtag->id]);

    $response->assertOk();
    expect(WorkspaceHashtag::find($hashtag->id))->toBeNull();
});

test('cannot delete hashtag from another workspace', function () {
    $otherWorkspace = Workspace::factory()->create();
    $hashtag = WorkspaceHashtag::factory()->create(['workspace_id' => $otherWorkspace->id]);

    $response = TryPostServer::actingAs($this->user)
        ->tool(DeleteHashtagTool::class, ['hashtag_id' => $hashtag->id]);

    $response->assertHasErrors(['Hashtag not found.']);
});

test('update hashtag validates required fields', function () {
    $response = TryPostServer::actingAs($this->user)
        ->tool(UpdateHashtagTool::class, []);

    $response->assertHasErrors();
});

test('delete hashtag validates hashtag_id required', function () {
    $response = TryPostServer::actingAs($this->user)
        ->tool(DeleteHashtagTool::class, []);

    $response->assertHasErrors();
});
