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
use Illuminate\Testing\Fluent\AssertableJson;

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

test('list posts returns wrapped posts array with PostResource shape', function () {
    Post::factory()->count(3)->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    $response = TryPostServer::actingAs($this->user)
        ->tool(ListPostsTool::class, []);

    $response->assertOk()
        ->assertStructuredContent(function (AssertableJson $json) {
            $json->has('posts', 3, function (AssertableJson $post) {
                $post->hasAll(['id', 'content', 'media', 'status', 'scheduled_at', 'published_at', 'platforms', 'labels', 'created_at', 'updated_at'])
                    ->missing('user_id')
                    ->missing('workspace_id');
            });
        });
});

test('list posts only returns own workspace posts', function () {
    Post::factory()->create(['workspace_id' => $this->workspace->id, 'user_id' => $this->user->id]);

    $otherWorkspace = Workspace::factory()->create();
    Post::factory()->create(['workspace_id' => $otherWorkspace->id, 'user_id' => $this->user->id]);

    $response = TryPostServer::actingAs($this->user)
        ->tool(ListPostsTool::class, []);

    $response->assertOk()
        ->assertStructuredContent(function (AssertableJson $json) {
            $json->has('posts', 1)->etc();
        });
});

test('get post returns PostResource shape', function () {
    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'content' => 'Hello world',
    ]);

    $response = TryPostServer::actingAs($this->user)
        ->tool(GetPostTool::class, ['post_id' => $post->id]);

    $response->assertOk()
        ->assertStructuredContent(function (AssertableJson $json) use ($post) {
            $json->where('id', $post->id)
                ->where('content', 'Hello world')
                ->missing('user_id')
                ->missing('workspace_id')
                ->etc();
        });
});

test('get post 404 from another workspace', function () {
    $otherWorkspace = Workspace::factory()->create();
    $post = Post::factory()->create(['workspace_id' => $otherWorkspace->id, 'user_id' => $this->user->id]);

    $response = TryPostServer::actingAs($this->user)
        ->tool(GetPostTool::class, ['post_id' => $post->id]);

    $response->assertHasErrors(['Post not found.']);
});

test('create post with content and date', function () {
    $response = TryPostServer::actingAs($this->user)
        ->tool(CreatePostTool::class, [
            'content' => 'My new post',
            'date' => '2026-04-15',
        ]);

    $response->assertOk()
        ->assertStructuredContent(function (AssertableJson $json) {
            $json->where('content', 'My new post')
                ->where('status', 'draft')
                ->where('scheduled_at', '2026-04-15 09:00:00')
                ->etc();
        });

    expect(Post::where('workspace_id', $this->workspace->id)->count())->toBe(1);
});

test('create post without args creates empty draft for today', function () {
    $response = TryPostServer::actingAs($this->user)
        ->tool(CreatePostTool::class, []);

    $response->assertOk()
        ->assertStructuredContent(function (AssertableJson $json) {
            $json->where('content', '')
                ->where('status', 'draft')
                ->etc();
        });

    expect(Post::where('workspace_id', $this->workspace->id)->count())->toBe(1);
});

test('create post rejects invalid date format', function () {
    $response = TryPostServer::actingAs($this->user)
        ->tool(CreatePostTool::class, ['date' => 'not-a-date']);

    $response->assertHasErrors();
});

test('delete post removes from db', function () {
    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    $response = TryPostServer::actingAs($this->user)
        ->tool(DeletePostTool::class, ['post_id' => $post->id]);

    $response->assertOk()
        ->assertStructuredContent(['deleted' => true]);

    expect(Post::find($post->id))->toBeNull();
});

test('delete post 404 from another workspace', function () {
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
