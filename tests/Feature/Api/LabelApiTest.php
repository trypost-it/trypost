<?php

declare(strict_types=1);

use App\Models\ApiToken;
use App\Models\Workspace;
use App\Models\WorkspaceLabel;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @return array{token: ApiToken, plain_token: string, workspace: Workspace}
 */
function createLabelApiToken(array $overrides = []): array
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

test('list labels', function () {
    $result = createLabelApiToken();

    WorkspaceLabel::factory()->count(3)->create([
        'workspace_id' => $result['workspace']->id,
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$result['plain_token'],
    ])->getJson(
        route('api.labels.index'),
        ['HTTP_HOST' => 'api.trypost.test']
    );

    $response->assertOk();
    $response->assertJsonCount(3);
});

test('create label', function () {
    $result = createLabelApiToken();

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$result['plain_token'],
    ])->postJson(
        route('api.labels.store'),
        [
            'name' => 'Marketing',
            'color' => '#FF0000',
        ],
        ['HTTP_HOST' => 'api.trypost.test']
    );

    $response->assertCreated();
    $response->assertJsonPath('name', 'Marketing');
    $response->assertJsonPath('color', '#FF0000');

    expect($result['workspace']->labels()->count())->toBe(1);
});

test('create label validation errors', function () {
    $result = createLabelApiToken();

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$result['plain_token'],
    ])->postJson(
        route('api.labels.store'),
        [],
        ['HTTP_HOST' => 'api.trypost.test']
    );

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['name', 'color']);
});

test('create label validates color format', function () {
    $result = createLabelApiToken();

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$result['plain_token'],
    ])->postJson(
        route('api.labels.store'),
        [
            'name' => 'Bad Color',
            'color' => 'not-a-color',
        ],
        ['HTTP_HOST' => 'api.trypost.test']
    );

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['color']);
});

test('update label', function () {
    $result = createLabelApiToken();

    $label = WorkspaceLabel::factory()->create([
        'workspace_id' => $result['workspace']->id,
        'name' => 'Old Name',
        'color' => '#000000',
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$result['plain_token'],
    ])->putJson(
        route('api.labels.update', $label),
        [
            'name' => 'Updated Name',
            'color' => '#FFFFFF',
        ],
        ['HTTP_HOST' => 'api.trypost.test']
    );

    $response->assertOk();
    $response->assertJsonPath('name', 'Updated Name');
    $response->assertJsonPath('color', '#FFFFFF');
});

test('delete label', function () {
    $result = createLabelApiToken();

    $label = WorkspaceLabel::factory()->create([
        'workspace_id' => $result['workspace']->id,
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$result['plain_token'],
    ])->deleteJson(
        route('api.labels.destroy', $label),
        [],
        ['HTTP_HOST' => 'api.trypost.test']
    );

    $response->assertNoContent();

    expect(WorkspaceLabel::find($label->id))->toBeNull();
});

test('cannot access labels from another workspace', function () {
    $result = createLabelApiToken();

    $otherWorkspace = Workspace::factory()->create();
    $label = WorkspaceLabel::factory()->create([
        'workspace_id' => $otherWorkspace->id,
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$result['plain_token'],
    ])->putJson(
        route('api.labels.update', $label),
        [
            'name' => 'Hacked Name',
            'color' => '#FF0000',
        ],
        ['HTTP_HOST' => 'api.trypost.test']
    );

    $response->assertNotFound();
});
