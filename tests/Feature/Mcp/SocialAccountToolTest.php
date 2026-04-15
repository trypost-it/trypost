<?php

declare(strict_types=1);

use App\Enums\SocialAccount\Platform;
use App\Enums\UserWorkspace\Role;
use App\Mcp\Servers\TryPostServer;
use App\Mcp\Tools\SocialAccount\ListSocialAccountsTool;
use App\Mcp\Tools\SocialAccount\ToggleSocialAccountTool;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Member->value]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);
});

// ListSocialAccountsTool

test('can list social accounts', function () {
    SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::LinkedIn,
    ]);
    SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::X,
    ]);

    $response = TryPostServer::actingAs($this->user)
        ->tool(ListSocialAccountsTool::class, []);

    $response->assertOk();
});

test('listing only returns own workspace accounts', function () {
    SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::LinkedIn,
    ]);

    $otherWorkspace = Workspace::factory()->create();
    SocialAccount::factory()->create([
        'workspace_id' => $otherWorkspace->id,
        'platform' => Platform::X,
    ]);

    $response = TryPostServer::actingAs($this->user)
        ->tool(ListSocialAccountsTool::class, []);

    $response->assertOk();
    $response->assertDontSee(Platform::X->value);
});

test('list does not expose tokens', function () {
    SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::LinkedIn,
        'access_token' => 'secret-token-123',
    ]);

    $response = TryPostServer::actingAs($this->user)
        ->tool(ListSocialAccountsTool::class, []);

    $response->assertOk();
    $response->assertDontSee('secret-token-123');
});

// ToggleSocialAccountTool

test('can toggle social account to inactive', function () {
    $account = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::LinkedIn,
        'is_active' => true,
    ]);

    $response = TryPostServer::actingAs($this->user)
        ->tool(ToggleSocialAccountTool::class, [
            'account_id' => $account->id,
        ]);

    $response->assertOk();
    expect($account->fresh()->is_active)->toBeFalse();
});

test('can toggle social account to active', function () {
    $account = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::LinkedIn,
        'is_active' => false,
    ]);

    $response = TryPostServer::actingAs($this->user)
        ->tool(ToggleSocialAccountTool::class, [
            'account_id' => $account->id,
        ]);

    $response->assertOk();
    expect($account->fresh()->is_active)->toBeTrue();
});

test('cannot toggle account from another workspace', function () {
    $otherWorkspace = Workspace::factory()->create();
    $account = SocialAccount::factory()->create([
        'workspace_id' => $otherWorkspace->id,
        'platform' => Platform::LinkedIn,
    ]);

    $response = TryPostServer::actingAs($this->user)
        ->tool(ToggleSocialAccountTool::class, [
            'account_id' => $account->id,
        ]);

    $response->assertHasErrors();
});

test('toggle validates account_id is required', function () {
    $response = TryPostServer::actingAs($this->user)
        ->tool(ToggleSocialAccountTool::class, []);

    $response->assertHasErrors();
});
