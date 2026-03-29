<?php

use App\Models\ApiToken;
use App\Models\Workspace;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @return array{token: ApiToken, plain_token: string, workspace: Workspace}
 */
function createApiToken(array $overrides = []): array
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

test('returns 401 without token', function () {
    $response = $this->getJson(
        route('api.workspace.show'),
        ['HTTP_HOST' => 'api.trypost.test']
    );

    $response->assertUnauthorized();
    $response->assertJson(['message' => 'Missing API key.']);
});

test('returns 401 with invalid token format', function () {
    $response = $this->withHeaders([
        'Authorization' => 'Bearer invalid-token',
    ])->getJson(
        route('api.workspace.show'),
        ['HTTP_HOST' => 'api.trypost.test']
    );

    $response->assertUnauthorized();
    $response->assertJson(['message' => 'Invalid API key.']);
});

test('returns 401 with wrong token', function () {
    createApiToken();

    $wrongToken = 'tp_'.Str::random(48);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$wrongToken,
    ])->getJson(
        route('api.workspace.show'),
        ['HTTP_HOST' => 'api.trypost.test']
    );

    $response->assertUnauthorized();
    $response->assertJson(['message' => 'Invalid API key.']);
});

test('returns 401 with expired token', function () {
    $result = createApiToken();

    $result['token']->update(['expires_at' => now()->subDay()]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$result['plain_token'],
    ])->getJson(
        route('api.workspace.show'),
        ['HTTP_HOST' => 'api.trypost.test']
    );

    $response->assertUnauthorized();
    $response->assertJson(['message' => 'API key has expired.']);
});

test('authenticates with valid token', function () {
    $result = createApiToken();

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$result['plain_token'],
    ])->getJson(
        route('api.workspace.show'),
        ['HTTP_HOST' => 'api.trypost.test']
    );

    $response->assertOk();
});

test('updates last_used_at on successful auth', function () {
    $this->freezeTime();

    $result = createApiToken();

    expect($result['token']->last_used_at)->toBeNull();

    $this->withHeaders([
        'Authorization' => 'Bearer '.$result['plain_token'],
    ])->getJson(
        route('api.workspace.show'),
        ['HTTP_HOST' => 'api.trypost.test']
    );

    $result['token']->refresh();

    expect($result['token']->last_used_at)->not->toBeNull();
    expect($result['token']->last_used_at->toDateTimeString())->toBe(now()->toDateTimeString());
});
