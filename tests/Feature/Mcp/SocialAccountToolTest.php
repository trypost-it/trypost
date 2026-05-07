<?php

declare(strict_types=1);

use App\Enums\SocialAccount\Platform;
use App\Enums\UserWorkspace\Role;
use App\Mcp\Servers\postproServer;
use App\Mcp\Tools\SocialAccount\ListSocialAccountsTool;
use App\Mcp\Tools\SocialAccount\ToggleSocialAccountTool;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Testing\Fluent\AssertableJson;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Member->value]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);
});

test('list returns wrapped social_accounts array with SocialAccountResource shape', function () {
    SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::LinkedIn,
    ]);
    SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::X,
    ]);

    $response = postproServer::actingAs($this->user)
        ->tool(ListSocialAccountsTool::class, []);

    $response->assertOk()
        ->assertStructuredContent(function (AssertableJson $json) {
            $json->has('social_accounts', 2, function (AssertableJson $account) {
                $account->hasAll(['id', 'platform', 'display_name', 'username', 'is_active', 'status'])
                    ->missing('access_token')
                    ->missing('refresh_token')
                    ->missing('workspace_id');
            });
        });
});

test('list only returns own workspace accounts', function () {
    SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::LinkedIn,
    ]);

    $otherWorkspace = Workspace::factory()->create();
    SocialAccount::factory()->create([
        'workspace_id' => $otherWorkspace->id,
        'platform' => Platform::X,
    ]);

    $response = postproServer::actingAs($this->user)
        ->tool(ListSocialAccountsTool::class, []);

    $response->assertOk()
        ->assertStructuredContent(function (AssertableJson $json) {
            $json->has('social_accounts', 1)->etc();
        });
});

test('list never exposes access_token or refresh_token', function () {
    SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::LinkedIn,
        'access_token' => 'secret-token-123',
    ]);

    $response = postproServer::actingAs($this->user)
        ->tool(ListSocialAccountsTool::class, []);

    $response->assertOk();
    $response->assertDontSee('secret-token-123');
});

test('toggle returns updated SocialAccountResource', function () {
    $account = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::LinkedIn,
        'is_active' => true,
    ]);

    $response = postproServer::actingAs($this->user)
        ->tool(ToggleSocialAccountTool::class, ['account_id' => $account->id]);

    $response->assertOk()
        ->assertStructuredContent(function (AssertableJson $json) use ($account) {
            $json->where('id', $account->id)
                ->where('is_active', false)
                ->where('platform', 'linkedin')
                ->etc();
        });

    expect($account->fresh()->is_active)->toBeFalse();
});

test('toggle inactive account becomes active', function () {
    $account = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::LinkedIn,
        'is_active' => false,
    ]);

    $response = postproServer::actingAs($this->user)
        ->tool(ToggleSocialAccountTool::class, ['account_id' => $account->id]);

    $response->assertOk();
    expect($account->fresh()->is_active)->toBeTrue();
});

test('cannot toggle account from another workspace', function () {
    $otherWorkspace = Workspace::factory()->create();
    $account = SocialAccount::factory()->create([
        'workspace_id' => $otherWorkspace->id,
        'platform' => Platform::LinkedIn,
    ]);

    $response = postproServer::actingAs($this->user)
        ->tool(ToggleSocialAccountTool::class, ['account_id' => $account->id]);

    $response->assertHasErrors(['Social account not found.']);
});

test('toggle validates account_id required', function () {
    $response = postproServer::actingAs($this->user)
        ->tool(ToggleSocialAccountTool::class, []);

    $response->assertHasErrors();
});

test('toggle validates account_id is uuid', function () {
    $response = postproServer::actingAs($this->user)
        ->tool(ToggleSocialAccountTool::class, ['account_id' => 'not-a-uuid']);

    $response->assertHasErrors();
});

