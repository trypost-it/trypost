<?php

declare(strict_types=1);

use App\Enums\SocialAccount\Platform;
use App\Enums\UserWorkspace\Role;
use App\Mcp\Servers\TryPostServer;
use App\Mcp\Tools\Post\CreatePostTool;
use App\Mcp\Tools\Post\DeletePostTool;
use App\Mcp\Tools\Post\GetPostTool;
use App\Mcp\Tools\Post\ListPostsTool;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Member->value]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);

    $this->socialAccount = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::LinkedIn,
    ]);
});

test('can list posts', function () {
    Post::factory()->count(3)->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    $response = TryPostServer::actingAs($this->user)
        ->tool(ListPostsTool::class, []);

    $response->assertOk();
});

test('listing only returns own workspace posts', function () {
    Post::factory()->create(['workspace_id' => $this->workspace->id, 'user_id' => $this->user->id]);

    $otherWorkspace = Workspace::factory()->create();
    Post::factory()->create(['workspace_id' => $otherWorkspace->id, 'user_id' => $this->user->id]);

    $response = TryPostServer::actingAs($this->user)
        ->tool(ListPostsTool::class, []);

    $response->assertOk();
});

test('can get a post by id', function () {
    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    $response = TryPostServer::actingAs($this->user)
        ->tool(GetPostTool::class, ['post_id' => $post->id]);

    $response->assertOk();
});

test('cannot get post from another workspace', function () {
    $otherWorkspace = Workspace::factory()->create();
    $post = Post::factory()->create(['workspace_id' => $otherWorkspace->id, 'user_id' => $this->user->id]);

    $response = TryPostServer::actingAs($this->user)
        ->tool(GetPostTool::class, ['post_id' => $post->id]);

    $response->assertHasErrors(['Post not found.']);
});

test('can create a post', function () {
    $response = TryPostServer::actingAs($this->user)
        ->tool(CreatePostTool::class, []);

    $response->assertOk();
    expect(Post::where('workspace_id', $this->workspace->id)->count())->toBe(1);
});

test('can create a post with date', function () {
    $response = TryPostServer::actingAs($this->user)
        ->tool(CreatePostTool::class, ['date' => '2026-04-15']);

    $response->assertOk();
});

test('can delete a post', function () {
    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    $response = TryPostServer::actingAs($this->user)
        ->tool(DeletePostTool::class, ['post_id' => $post->id]);

    $response->assertOk();
    expect(Post::find($post->id))->toBeNull();
});

test('cannot delete post from another workspace', function () {
    $otherWorkspace = Workspace::factory()->create();
    $post = Post::factory()->create(['workspace_id' => $otherWorkspace->id, 'user_id' => $this->user->id]);

    $response = TryPostServer::actingAs($this->user)
        ->tool(DeletePostTool::class, ['post_id' => $post->id]);

    $response->assertHasErrors(['Post not found.']);
});

test('get post validates post_id required', function () {
    $response = TryPostServer::actingAs($this->user)
        ->tool(GetPostTool::class, []);

    $response->assertHasErrors();
});

test('delete post validates post_id required', function () {
    $response = TryPostServer::actingAs($this->user)
        ->tool(DeletePostTool::class, []);

    $response->assertHasErrors();
});
