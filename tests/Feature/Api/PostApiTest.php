<?php

declare(strict_types=1);

use App\Enums\Post\Status as PostStatus;
use App\Enums\PostPlatform\ContentType;
use App\Enums\SocialAccount\Platform;
use App\Enums\UserWorkspace\Role;
use App\Models\ApiToken;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Member->value]);

    $plainToken = 'tp_'.Str::random(48);
    $this->plainToken = $plainToken;
    $this->apiToken = ApiToken::factory()->create([
        'workspace_id' => $this->workspace->id,
        'token_lookup' => substr($plainToken, 3, 16),
        'token_hash' => Hash::make($plainToken),
    ]);

    $this->socialAccount = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::LinkedIn,
    ]);
});

it('lists posts', function () {
    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    $this->withHeaders(['Authorization' => 'Bearer '.$this->plainToken])
        ->getJson(route('api.posts.index'))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('shows a post', function () {
    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    $this->withHeaders(['Authorization' => 'Bearer '.$this->plainToken])
        ->getJson(route('api.posts.show', $post))
        ->assertOk()
        ->assertJsonPath('id', $post->id);
});

it('cannot show post from another workspace', function () {
    $otherWorkspace = Workspace::factory()->create();
    $post = Post::factory()->create([
        'workspace_id' => $otherWorkspace->id,
        'user_id' => $this->user->id,
    ]);

    $this->withHeaders(['Authorization' => 'Bearer '.$this->plainToken])
        ->getJson(route('api.posts.show', $post))
        ->assertNotFound();
});

it('creates a post', function () {
    $this->withHeaders(['Authorization' => 'Bearer '.$this->plainToken])
        ->postJson(route('api.posts.store'), [
            'platforms' => [
                [
                    'social_account_id' => $this->socialAccount->id,
                    'content_type' => 'linkedin_post',
                ],
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('status', PostStatus::Draft->value);

    expect(Post::where('workspace_id', $this->workspace->id)->count())->toBe(1);
});

it('deletes a post', function () {
    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    $this->withHeaders(['Authorization' => 'Bearer '.$this->plainToken])
        ->deleteJson(route('api.posts.destroy', $post))
        ->assertNoContent();

    expect(Post::find($post->id))->toBeNull();
});

it('cannot delete post from another workspace', function () {
    $otherWorkspace = Workspace::factory()->create();
    $post = Post::factory()->create([
        'workspace_id' => $otherWorkspace->id,
        'user_id' => $this->user->id,
    ]);

    $this->withHeaders(['Authorization' => 'Bearer '.$this->plainToken])
        ->deleteJson(route('api.posts.destroy', $post))
        ->assertNotFound();
});

it('updates a post', function () {
    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'status' => PostStatus::Draft,
    ]);

    $postPlatform = PostPlatform::factory()->linkedin()->create([
        'post_id' => $post->id,
        'social_account_id' => $this->socialAccount->id,
        'enabled' => true,
    ]);

    $this->withHeaders(['Authorization' => 'Bearer '.$this->plainToken])
        ->putJson(route('api.posts.update', $post), [
            'status' => 'draft',
            'platforms' => [
                [
                    'id' => $postPlatform->id,
                    'content_type' => ContentType::LinkedInPost->value,
                ],
            ],
        ])
        ->assertOk();
});

it('cannot update post from another workspace', function () {
    $otherWorkspace = Workspace::factory()->create();
    $otherSocialAccount = SocialAccount::factory()->create([
        'workspace_id' => $otherWorkspace->id,
        'platform' => Platform::LinkedIn,
    ]);
    $post = Post::factory()->create([
        'workspace_id' => $otherWorkspace->id,
        'user_id' => $this->user->id,
    ]);
    $postPlatform = PostPlatform::factory()->linkedin()->create([
        'post_id' => $post->id,
        'social_account_id' => $otherSocialAccount->id,
        'enabled' => true,
    ]);

    $this->withHeaders(['Authorization' => 'Bearer '.$this->plainToken])
        ->putJson(route('api.posts.update', $post), [
            'status' => 'draft',
            'platforms' => [
                [
                    'id' => $postPlatform->id,
                    'content_type' => ContentType::LinkedInPost->value,
                ],
            ],
        ])
        ->assertNotFound();
});

it('cannot update published post', function () {
    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'status' => PostStatus::Published,
    ]);

    $postPlatform = PostPlatform::factory()->linkedin()->create([
        'post_id' => $post->id,
        'social_account_id' => $this->socialAccount->id,
        'enabled' => true,
    ]);

    $this->withHeaders(['Authorization' => 'Bearer '.$this->plainToken])
        ->putJson(route('api.posts.update', $post), [
            'status' => 'draft',
            'platforms' => [
                [
                    'id' => $postPlatform->id,
                    'content_type' => ContentType::LinkedInPost->value,
                ],
            ],
        ])
        ->assertUnprocessable();
});

it('validates post update fields', function () {
    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    $this->withHeaders(['Authorization' => 'Bearer '.$this->plainToken])
        ->putJson(route('api.posts.update', $post), [
            'platforms' => [
                ['content' => 'missing id'],
            ],
        ])
        ->assertUnprocessable();
});

it('validates post creation requires platforms', function () {
    $this->withHeaders(['Authorization' => 'Bearer '.$this->plainToken])
        ->postJson(route('api.posts.store'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['platforms']);
});

it('validates post creation platform fields', function () {
    $this->withHeaders(['Authorization' => 'Bearer '.$this->plainToken])
        ->postJson(route('api.posts.store'), [
            'platforms' => [
                ['content' => 'missing social_account_id and content_type'],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['platforms.0.social_account_id', 'platforms.0.content_type']);
});

it('validates post update invalid status', function () {
    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    $this->withHeaders(['Authorization' => 'Bearer '.$this->plainToken])
        ->putJson(route('api.posts.update', $post), [
            'status' => 'invalid_status',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

it('validates post update scheduled_at must be date', function () {
    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    $this->withHeaders(['Authorization' => 'Bearer '.$this->plainToken])
        ->putJson(route('api.posts.update', $post), [
            'scheduled_at' => 'not-a-date',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['scheduled_at']);
});

it('validates post update label_ids must be uuids', function () {
    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    $this->withHeaders(['Authorization' => 'Bearer '.$this->plainToken])
        ->putJson(route('api.posts.update', $post), [
            'label_ids' => ['not-a-uuid'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['label_ids.0']);
});

it('list posts returns correct structure', function () {
    Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    $this->withHeaders(['Authorization' => 'Bearer '.$this->plainToken])
        ->getJson(route('api.posts.index'))
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'status', 'scheduled_at', 'published_at', 'created_at', 'updated_at'],
            ],
        ]);
});

it('show post returns correct structure', function () {
    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    $this->withHeaders(['Authorization' => 'Bearer '.$this->plainToken])
        ->getJson(route('api.posts.show', $post))
        ->assertOk()
        ->assertJsonStructure(['id', 'status', 'scheduled_at', 'published_at']);
});
