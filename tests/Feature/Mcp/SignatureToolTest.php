<?php

declare(strict_types=1);

use App\Enums\UserWorkspace\Role;
use App\Mcp\Servers\TryPostServer;
use App\Mcp\Tools\Signature\CreateSignatureTool;
use App\Mcp\Tools\Signature\DeleteSignatureTool;
use App\Mcp\Tools\Signature\ListSignaturesTool;
use App\Mcp\Tools\Signature\UpdateSignatureTool;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceSignature;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Member->value]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);
});

test('can list signatures', function () {
    WorkspaceSignature::factory()->count(2)->create(['workspace_id' => $this->workspace->id]);

    $response = TryPostServer::actingAs($this->user)
        ->tool(ListSignaturesTool::class, []);

    $response->assertOk();
});

test('can create signature', function () {
    $response = TryPostServer::actingAs($this->user)
        ->tool(CreateSignatureTool::class, [
            'name' => 'Marketing',
            'content' => '#marketing #social',
        ]);

    $response->assertOk();
    $response->assertSee('Marketing');
    expect($this->workspace->signatures()->count())->toBe(1);
});

test('create signature validates required fields', function () {
    $response = TryPostServer::actingAs($this->user)
        ->tool(CreateSignatureTool::class, []);

    $response->assertHasErrors();
});

test('can update signature', function () {
    $signature = WorkspaceSignature::factory()->create(['workspace_id' => $this->workspace->id]);

    $response = TryPostServer::actingAs($this->user)
        ->tool(UpdateSignatureTool::class, [
            'signature_id' => $signature->id,
            'name' => 'Updated',
            'content' => '#updated',
        ]);

    $response->assertOk();
    $response->assertSee('Updated');
});

test('cannot update signature from another workspace', function () {
    $otherWorkspace = Workspace::factory()->create();
    $signature = WorkspaceSignature::factory()->create(['workspace_id' => $otherWorkspace->id]);

    $response = TryPostServer::actingAs($this->user)
        ->tool(UpdateSignatureTool::class, [
            'signature_id' => $signature->id,
            'name' => 'Hacked',
            'content' => '#hacked',
        ]);

    $response->assertHasErrors(['Signature not found.']);
});

test('can delete signature', function () {
    $signature = WorkspaceSignature::factory()->create(['workspace_id' => $this->workspace->id]);

    $response = TryPostServer::actingAs($this->user)
        ->tool(DeleteSignatureTool::class, ['signature_id' => $signature->id]);

    $response->assertOk();
    expect(WorkspaceSignature::find($signature->id))->toBeNull();
});

test('cannot delete signature from another workspace', function () {
    $otherWorkspace = Workspace::factory()->create();
    $signature = WorkspaceSignature::factory()->create(['workspace_id' => $otherWorkspace->id]);

    $response = TryPostServer::actingAs($this->user)
        ->tool(DeleteSignatureTool::class, ['signature_id' => $signature->id]);

    $response->assertHasErrors(['Signature not found.']);
});

test('update signature validates required fields', function () {
    $response = TryPostServer::actingAs($this->user)
        ->tool(UpdateSignatureTool::class, []);

    $response->assertHasErrors();
});

test('delete signature validates signature_id required', function () {
    $response = TryPostServer::actingAs($this->user)
        ->tool(DeleteSignatureTool::class, []);

    $response->assertHasErrors();
});
