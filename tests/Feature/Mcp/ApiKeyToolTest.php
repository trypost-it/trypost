<?php

declare(strict_types=1);

use App\Enums\UserWorkspace\Role;
use App\Mcp\Servers\TryPostServer;
use App\Mcp\Tools\ApiKey\CreateApiKeyTool;
use App\Mcp\Tools\ApiKey\DeleteApiKeyTool;
use App\Mcp\Tools\ApiKey\ListApiKeysTool;
use App\Models\ApiToken;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Owner->value]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);
});

test('can list api keys', function () {
    ApiToken::factory()->count(2)->create(['workspace_id' => $this->workspace->id]);

    $response = TryPostServer::actingAs($this->user)
        ->tool(ListApiKeysTool::class, []);

    $response->assertOk();
});

test('can create api key', function () {
    $response = TryPostServer::actingAs($this->user)
        ->tool(CreateApiKeyTool::class, [
            'name' => 'My Key',
        ]);

    $response->assertOk();
    $response->assertSee('My Key');
    expect($this->workspace->apiTokens()->count())->toBe(1);
});

test('create api key validates name required', function () {
    $response = TryPostServer::actingAs($this->user)
        ->tool(CreateApiKeyTool::class, []);

    $response->assertHasErrors();
});

test('can delete api key', function () {
    $token = ApiToken::factory()->create(['workspace_id' => $this->workspace->id]);

    $response = TryPostServer::actingAs($this->user)
        ->tool(DeleteApiKeyTool::class, ['api_key_id' => $token->id]);

    $response->assertOk();
    expect(ApiToken::find($token->id))->toBeNull();
});

test('cannot delete api key from another workspace', function () {
    $otherWorkspace = Workspace::factory()->create();
    $token = ApiToken::factory()->create(['workspace_id' => $otherWorkspace->id]);

    $response = TryPostServer::actingAs($this->user)
        ->tool(DeleteApiKeyTool::class, ['api_key_id' => $token->id]);

    $response->assertHasErrors(['API key not found.']);
});

test('delete api key validates api_key_id required', function () {
    $response = TryPostServer::actingAs($this->user)
        ->tool(DeleteApiKeyTool::class, []);

    $response->assertHasErrors();
});
