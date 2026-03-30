<?php

declare(strict_types=1);

use App\Enums\Post\Status as PostStatus;
use App\Enums\SocialAccount\Platform;
use App\Models\ApiToken;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->workspace->members()->attach($this->user->id, ['role' => 'owner']);

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
