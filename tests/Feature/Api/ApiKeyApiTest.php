<?php

declare(strict_types=1);

use App\Models\ApiToken;
use App\Models\Workspace;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @return array{token: ApiToken, plain_token: string, workspace: Workspace}
 */
function createApiKeyApiToken(array $overrides = []): array
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

test('list api keys', function () {
    $result = createApiKeyApiToken();

    // The authenticating token itself is one, create two more
    ApiToken::factory()->count(2)->create([
        'workspace_id' => $result['workspace']->id,
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$result['plain_token'],
    ])->getJson(
        route('api.api-keys.index'),
        ['HTTP_HOST' => 'api.trypost.test']
    );

    $response->assertOk();
    $response->assertJsonCount(3);
});

test('create api key returns plain token', function () {
    $result = createApiKeyApiToken();

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$result['plain_token'],
    ])->postJson(
        route('api.api-keys.store'),
        [
            'name' => 'CI/CD Token',
        ],
        ['HTTP_HOST' => 'api.trypost.test']
    );

    $response->assertCreated();
    $response->assertJsonStructure([
        'token' => ['id', 'name', 'key_hint', 'status'],
        'plain_token',
    ]);

    $plainToken = $response->json('plain_token');
    expect($plainToken)->toStartWith('tp_');
    expect(strlen($plainToken))->toBe(51);
});

test('create api key validation errors', function () {
    $result = createApiKeyApiToken();

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$result['plain_token'],
    ])->postJson(
        route('api.api-keys.store'),
        [],
        ['HTTP_HOST' => 'api.trypost.test']
    );

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['name']);
});

test('delete api key', function () {
    $result = createApiKeyApiToken();

    $tokenToDelete = ApiToken::factory()->create([
        'workspace_id' => $result['workspace']->id,
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$result['plain_token'],
    ])->deleteJson(
        route('api.api-keys.destroy', $tokenToDelete),
        [],
        ['HTTP_HOST' => 'api.trypost.test']
    );

    $response->assertNoContent();

    expect(ApiToken::find($tokenToDelete->id))->toBeNull();
});

test('cannot delete api key from another workspace', function () {
    $result = createApiKeyApiToken();

    $otherWorkspace = Workspace::factory()->create();
    $otherToken = ApiToken::factory()->create([
        'workspace_id' => $otherWorkspace->id,
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$result['plain_token'],
    ])->deleteJson(
        route('api.api-keys.destroy', $otherToken),
        [],
        ['HTTP_HOST' => 'api.trypost.test']
    );

    $response->assertNotFound();
});
