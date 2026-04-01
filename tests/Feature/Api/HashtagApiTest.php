<?php

declare(strict_types=1);

use App\Models\ApiToken;
use App\Models\Workspace;
use App\Models\WorkspaceHashtag;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @return array{token: ApiToken, plain_token: string, workspace: Workspace}
 */
function createHashtagApiToken(array $overrides = []): array
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

test('list hashtags', function () {
    $result = createHashtagApiToken();

    WorkspaceHashtag::factory()->count(3)->create([
        'workspace_id' => $result['workspace']->id,
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$result['plain_token'],
    ])->getJson(
        route('api.hashtags.index'),
        ['HTTP_HOST' => 'api.trypost.test']
    );

    $response->assertOk();
    $response->assertJsonCount(3);
});

test('create hashtag', function () {
    $result = createHashtagApiToken();

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$result['plain_token'],
    ])->postJson(
        route('api.hashtags.store'),
        [
            'name' => 'Marketing Tags',
            'hashtags' => '#marketing #growth #saas',
        ],
        ['HTTP_HOST' => 'api.trypost.test']
    );

    $response->assertCreated();
    $response->assertJsonPath('name', 'Marketing Tags');

    expect($result['workspace']->hashtags()->count())->toBe(1);
});

test('create hashtag validation errors', function () {
    $result = createHashtagApiToken();

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$result['plain_token'],
    ])->postJson(
        route('api.hashtags.store'),
        [],
        ['HTTP_HOST' => 'api.trypost.test']
    );

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['name', 'hashtags']);
});

test('update hashtag', function () {
    $result = createHashtagApiToken();

    $hashtag = WorkspaceHashtag::factory()->create([
        'workspace_id' => $result['workspace']->id,
        'name' => 'Old Name',
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$result['plain_token'],
    ])->putJson(
        route('api.hashtags.update', $hashtag),
        [
            'name' => 'Updated Name',
            'hashtags' => '#updated #tags',
        ],
        ['HTTP_HOST' => 'api.trypost.test']
    );

    $response->assertOk();
    $response->assertJsonPath('name', 'Updated Name');
});

test('delete hashtag', function () {
    $result = createHashtagApiToken();

    $hashtag = WorkspaceHashtag::factory()->create([
        'workspace_id' => $result['workspace']->id,
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$result['plain_token'],
    ])->deleteJson(
        route('api.hashtags.destroy', $hashtag),
        [],
        ['HTTP_HOST' => 'api.trypost.test']
    );

    $response->assertNoContent();

    expect(WorkspaceHashtag::find($hashtag->id))->toBeNull();
});

test('cannot access hashtags from another workspace', function () {
    $result = createHashtagApiToken();

    $otherWorkspace = Workspace::factory()->create();
    $hashtag = WorkspaceHashtag::factory()->create([
        'workspace_id' => $otherWorkspace->id,
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$result['plain_token'],
    ])->putJson(
        route('api.hashtags.update', $hashtag),
        [
            'name' => 'Hacked Name',
            'hashtags' => '#hacked',
        ],
        ['HTTP_HOST' => 'api.trypost.test']
    );

    $response->assertNotFound();
});

test('cannot update hashtag from another workspace', function () {
    $result = createHashtagApiToken();
    $otherWorkspace = Workspace::factory()->create();
    $hashtag = WorkspaceHashtag::factory()->create(['workspace_id' => $otherWorkspace->id]);

    $this->withHeaders(['Authorization' => 'Bearer '.data_get($result, 'plain_token')])
        ->putJson(route('api.hashtags.update', $hashtag), [
            'name' => 'Hacked',
            'hashtags' => '#hacked',
        ])
        ->assertNotFound();
});

test('update hashtag validation errors', function () {
    $result = createHashtagApiToken();
    $hashtag = WorkspaceHashtag::factory()->create(['workspace_id' => data_get($result, 'workspace')->id]);

    $this->withHeaders(['Authorization' => 'Bearer '.data_get($result, 'plain_token')])
        ->putJson(route('api.hashtags.update', $hashtag), [])
        ->assertUnprocessable();
});

test('list hashtags returns correct structure', function () {
    $result = createHashtagApiToken();
    WorkspaceHashtag::factory()->create(['workspace_id' => data_get($result, 'workspace')->id]);

    $this->withHeaders(['Authorization' => 'Bearer '.data_get($result, 'plain_token')])
        ->getJson(route('api.hashtags.index'))
        ->assertOk()
        ->assertJsonStructure([
            '*' => ['id', 'name', 'hashtags', 'created_at', 'updated_at'],
        ]);
});

test('cannot delete hashtag from another workspace', function () {
    $result = createHashtagApiToken();
    $otherWorkspace = Workspace::factory()->create();
    $hashtag = WorkspaceHashtag::factory()->create(['workspace_id' => $otherWorkspace->id]);

    $this->withHeaders(['Authorization' => 'Bearer '.data_get($result, 'plain_token')])
        ->deleteJson(route('api.hashtags.destroy', $hashtag))
        ->assertNotFound();
});
