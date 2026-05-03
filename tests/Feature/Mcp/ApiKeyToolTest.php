<?php

declare(strict_types=1);

use App\Enums\UserWorkspace\Role;
use App\Mcp\Servers\TryPostServer;
use App\Mcp\Tools\ApiKey\CreateApiKeyTool;
use App\Mcp\Tools\ApiKey\DeleteApiKeyTool;
use App\Mcp\Tools\ApiKey\ListApiKeysTool;
use App\Models\AccessToken;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create([
        'account_id' => $this->user->account_id,
        'user_id' => $this->user->id,
    ]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Admin->value]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);
    $this->user->refresh();
});

function attachToken(User $user, Workspace $workspace): AccessToken
{
    $result = $user->createToken('Existing');
    $token = AccessToken::find($result->token->id);
    $token->forceFill(['workspace_id' => $workspace->id])->saveQuietly();

    return $token->refresh();
}

test('can list api keys', function () {
    attachToken($this->user, $this->workspace);
    attachToken($this->user, $this->workspace);

    $response = TryPostServer::actingAs($this->user)
        ->tool(ListApiKeysTool::class, []);

    $response->assertOk();
});

test('can create api key', function () {
    $response = TryPostServer::actingAs($this->user)
        ->tool(CreateApiKeyTool::class, ['name' => 'My Key']);

    $response->assertOk();
    $response->assertSee('My Key');

    expect(AccessToken::where('user_id', $this->user->id)
        ->where('workspace_id', $this->workspace->id)
        ->count())->toBe(1);
});

test('create api key validates name required', function () {
    $response = TryPostServer::actingAs($this->user)
        ->tool(CreateApiKeyTool::class, []);

    $response->assertHasErrors();
});

test('can revoke api key', function () {
    $token = attachToken($this->user, $this->workspace);

    $response = TryPostServer::actingAs($this->user)
        ->tool(DeleteApiKeyTool::class, ['api_key_id' => $token->id]);

    $response->assertOk();
    expect($token->refresh()->revoked)->toBeTrue();
});

test('cannot delete api key from another workspace', function () {
    $otherUser = User::factory()->create();
    $otherWorkspace = Workspace::factory()->create([
        'account_id' => $otherUser->account_id,
        'user_id' => $otherUser->id,
    ]);
    $token = attachToken($otherUser, $otherWorkspace);

    $response = TryPostServer::actingAs($this->user)
        ->tool(DeleteApiKeyTool::class, ['api_key_id' => $token->id]);

    $response->assertHasErrors(['API key not found.']);
});

test('delete api key validates api_key_id required', function () {
    $response = TryPostServer::actingAs($this->user)
        ->tool(DeleteApiKeyTool::class, []);

    $response->assertHasErrors();
});
