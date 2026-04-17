<?php

declare(strict_types=1);

use App\Models\ApiToken;
use App\Models\Workspace;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @return array{token: ApiToken, plain_token: string, workspace: Workspace}
 */
function createWorkspaceApiToken(array $overrides = []): array
{
    $plainToken = 'tp_'.Str::random(48);

    $workspace = data_get($overrides, 'workspace') ?? Workspace::factory()->create();

    $factoryOverrides = collect($overrides)->except('workspace')->toArray();

    $apiToken = ApiToken::factory()->create(array_merge([
        'workspace_id' => $workspace->id,
        'token_lookup' => substr($plainToken, 3, 16),
        'token_hash' => Hash::make($plainToken),
    ], $factoryOverrides));

    return [
        'token' => $apiToken,
        'plain_token' => $plainToken,
        'workspace' => $workspace,
    ];
}

test('show current workspace', function () {
    $workspace = Workspace::factory()->create([
        'name' => 'Test Workspace',
    ]);

    $result = createWorkspaceApiToken(['workspace' => $workspace]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$result['plain_token'],
    ])->getJson(
        route('api.workspace.show'),
        ['HTTP_HOST' => 'api.trypost.test']
    );

    $response->assertOk();
    $response->assertJsonPath('name', 'Test Workspace');
});

test('show workspace requires authentication', function () {
    $response = $this->getJson(
        route('api.workspace.show'),
        ['HTTP_HOST' => 'api.trypost.test']
    );

    $response->assertUnauthorized();
});

test('workspace returns correct structure', function () {
    $result = createWorkspaceApiToken();

    $this->withHeaders(['Authorization' => 'Bearer '.data_get($result, 'plain_token')])
        ->getJson(route('api.workspace.show'))
        ->assertOk()
        ->assertJsonStructure(['id', 'name', 'created_at', 'updated_at']);
});
