<?php

declare(strict_types=1);

use App\Enums\SocialAccount\Platform;
use App\Models\SocialAccount;
use App\Models\Workspace;

beforeEach(function () {
    $result = createApiTestToken();
    $this->user = $result['user'];
    $this->workspace = $result['workspace'];
    $this->plainToken = $result['plain_token'];
});

it('lists social accounts', function () {
    SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::LinkedIn,
    ]);
    SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::X,
    ]);

    $response = $this->getJson(route('api.social-accounts.index'), [
        'Authorization' => "Bearer {$this->plainToken}",
    ]);

    $response->assertOk();
    $response->assertJsonCount(2);
    $response->assertJsonStructure([
        '*' => ['id', 'platform', 'display_name', 'username', 'is_active', 'status'],
    ]);
});

it('does not expose tokens in social accounts list', function () {
    SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::LinkedIn,
    ]);

    $response = $this->getJson(route('api.social-accounts.index'), [
        'Authorization' => "Bearer {$this->plainToken}",
    ]);

    $response->assertOk();
    $response->assertJsonMissing(['access_token']);
    $response->assertJsonMissing(['refresh_token']);
});

it('toggles social account from active to inactive', function () {
    $account = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::LinkedIn,
        'is_active' => true,
    ]);

    $response = $this->putJson(route('api.social-accounts.toggle', $account), [], [
        'Authorization' => "Bearer {$this->plainToken}",
    ]);

    $response->assertOk();
    $response->assertJsonPath('is_active', false);
    expect($account->fresh()->is_active)->toBeFalse();
});

it('toggles social account from inactive to active', function () {
    $account = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::LinkedIn,
        'is_active' => false,
    ]);

    $response = $this->putJson(route('api.social-accounts.toggle', $account), [], [
        'Authorization' => "Bearer {$this->plainToken}",
    ]);

    $response->assertOk();
    $response->assertJsonPath('is_active', true);
    expect($account->fresh()->is_active)->toBeTrue();
});

it('cannot toggle social account from another workspace', function () {
    $otherWorkspace = Workspace::factory()->create();
    $account = SocialAccount::factory()->create([
        'workspace_id' => $otherWorkspace->id,
        'platform' => Platform::LinkedIn,
    ]);

    $response = $this->putJson(route('api.social-accounts.toggle', $account), [], [
        'Authorization' => "Bearer {$this->plainToken}",
    ]);

    $response->assertNotFound();
});

it('requires authentication to list social accounts', function () {
    $response = $this->getJson(route('api.social-accounts.index'));

    $response->assertUnauthorized();
});

it('requires authentication to toggle social account', function () {
    $account = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
    ]);

    $response = $this->putJson(route('api.social-accounts.toggle', $account));

    $response->assertUnauthorized();
});

it('returns empty list when no social accounts', function () {
    $response = $this->getJson(route('api.social-accounts.index'), [
        'Authorization' => "Bearer {$this->plainToken}",
    ]);

    $response->assertOk();
    $response->assertJsonCount(0);
});
