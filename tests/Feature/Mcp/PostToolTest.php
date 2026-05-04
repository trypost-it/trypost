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
use App\Models\WorkspaceLabel;
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
            'scheduled_at' => '2099-12-31T15:30:00Z',
        ]);

    $response->assertOk()
        ->assertStructuredContent(function (AssertableJson $json) {
            $json->where('content', 'My new post')
                ->where('status', 'draft')
                ->where('scheduled_at', '2099-12-31 15:30:00')
                ->etc();
        });

    expect(Post::where('workspace_id', $this->workspace->id)->count())->toBe(1);
});

test('create post with platforms enables only those', function () {
    $response = TryPostServer::actingAs($this->user)
        ->tool(CreatePostTool::class, [
            'content' => 'with platforms',
            'platforms' => [
                ['social_account_id' => $this->socialAccount->id, 'content_type' => 'linkedin_post'],
            ],
        ]);

    $response->assertOk();

    $post = Post::where('workspace_id', $this->workspace->id)->first();
    $enabled = $post->postPlatforms()->where('enabled', true)->get();
    expect($enabled)->toHaveCount(1);
    expect($enabled->first()->social_account_id)->toBe($this->socialAccount->id);
    expect($enabled->first()->content_type->value)->toBe('linkedin_post');
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

test('create post rejects scheduled_at in the past', function () {
    $response = TryPostServer::actingAs($this->user)
        ->tool(CreatePostTool::class, ['scheduled_at' => '2020-01-01T00:00:00Z']);

    $response->assertHasErrors();
});

test('create post rejects an inactive social account', function () {
    $inactive = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::LinkedIn,
        'is_active' => false,
    ]);

    $response = TryPostServer::actingAs($this->user)
        ->tool(CreatePostTool::class, [
            'platforms' => [
                ['social_account_id' => $inactive->id, 'content_type' => 'linkedin_post'],
            ],
        ]);

    $response->assertHasErrors();
});

test('create post rejects a content_type not in the enum', function () {
    $response = TryPostServer::actingAs($this->user)
        ->tool(CreatePostTool::class, [
            'platforms' => [
                ['social_account_id' => $this->socialAccount->id, 'content_type' => 'made_up_type'],
            ],
        ]);

    $response->assertHasErrors();
});

test('create post rejects a content_type that does not match the social account platform', function () {
    // x_post on a LinkedIn account — ContentTypeMatchesPlatform should reject.
    $response = TryPostServer::actingAs($this->user)
        ->tool(CreatePostTool::class, [
            'platforms' => [
                ['social_account_id' => $this->socialAccount->id, 'content_type' => 'x_post'],
            ],
        ]);

    $response->assertHasErrors();
});

test('create post rejects a label_id from another workspace', function () {
    $otherWorkspace = Workspace::factory()->create();
    $foreignLabel = WorkspaceLabel::factory()->create(['workspace_id' => $otherWorkspace->id]);

    $response = TryPostServer::actingAs($this->user)
        ->tool(CreatePostTool::class, [
            'platforms' => [
                ['social_account_id' => $this->socialAccount->id, 'content_type' => 'linkedin_post'],
            ],
            'label_ids' => [$foreignLabel->id],
        ]);

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
