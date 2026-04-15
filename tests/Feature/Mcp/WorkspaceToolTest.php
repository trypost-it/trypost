<?php

declare(strict_types=1);

use App\Enums\UserWorkspace\Role;
use App\Mcp\Servers\TryPostServer;
use App\Mcp\Tools\Workspace\GetWorkspaceTool;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Member->value]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);
});

test('can get workspace details', function () {
    $response = TryPostServer::actingAs($this->user)
        ->tool(GetWorkspaceTool::class, []);

    $response->assertOk();
    $response->assertSee($this->workspace->name);
});
