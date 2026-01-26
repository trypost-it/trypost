<?php

use App\Enums\Post\Status as PostStatus;
use App\Enums\PostPlatform\ContentType;
use App\Enums\SocialAccount\Platform;
use App\Enums\User\Setup;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    $this->user = User::factory()->create(['setup' => Setup::Completed]);
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);

    $this->socialAccount = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::LinkedIn,
    ]);
});

// Index tests
test('posts index requires authentication', function () {
    $response = $this->get(route('posts.index'));

    $response->assertRedirect(route('login'));
});

test('posts index shows posts for current workspace', function () {
    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)->get(route('posts.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('posts/Index', false)
        ->has('posts.data', 1)
    );
});

test('posts index redirects to create workspace if no workspace', function () {
    $this->user->update(['current_workspace_id' => null]);

    $response = $this->actingAs($this->user)->get(route('posts.index'));

    $response->assertRedirect(route('workspaces.create'));
});

// Calendar tests
test('calendar requires authentication', function () {
    $response = $this->get(route('calendar'));

    $response->assertRedirect(route('login'));
});

test('calendar shows posts for current week', function () {
    $response = $this->actingAs($this->user)->get(route('calendar'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('posts/Calendar')
        ->has('workspace')
        ->has('posts')
        ->has('currentWeekStart')
        ->has('view')
    );
});

test('calendar supports month view', function () {
    $response = $this->actingAs($this->user)->get(route('calendar', ['view' => 'month']));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('view', 'month')
    );
});

// Store tests
test('store post requires authentication', function () {
    $response = $this->post(route('posts.store'));

    $response->assertRedirect(route('login'));
});

test('store post redirects to accounts if no social accounts connected', function () {
    $this->socialAccount->delete();

    $response = $this->actingAs($this->user)->post(route('posts.store'));

    $response->assertRedirect(route('accounts'));
});

test('store post creates draft and redirects to edit', function () {
    $response = $this->actingAs($this->user)->post(route('posts.store'));

    $response->assertRedirect();

    $post = Post::where('workspace_id', $this->workspace->id)->first();
    expect($post)->not->toBeNull();
    expect($post->status)->toBe(PostStatus::Draft);
    expect($post->postPlatforms)->toHaveCount(1);
});

// Edit tests
test('edit post requires authentication', function () {
    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    $response = $this->get(route('posts.edit', $post));

    $response->assertRedirect(route('login'));
});

test('edit post shows edit page', function () {
    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'status' => PostStatus::Draft,
    ]);

    PostPlatform::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $this->socialAccount->id,
    ]);

    $response = $this->actingAs($this->user)->get(route('posts.edit', $post));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('posts/Edit')
        ->has('post')
        ->has('socialAccounts')
    );
});

test('edit post returns 404 for post from different workspace', function () {
    $otherWorkspace = Workspace::factory()->create();
    $post = Post::factory()->create([
        'workspace_id' => $otherWorkspace->id,
        'user_id' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)->get(route('posts.edit', $post));

    $response->assertNotFound();
});

test('edit post shows published posts in read-only mode', function () {
    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'status' => PostStatus::Published,
    ]);

    PostPlatform::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $this->socialAccount->id,
    ]);

    $response = $this->actingAs($this->user)->get(route('posts.edit', $post));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('posts/Edit')
        ->has('post')
    );
});

// Update tests
test('update post requires authentication', function () {
    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    $response = $this->put(route('posts.update', $post), []);

    $response->assertRedirect(route('login'));
});

test('update post saves changes', function () {
    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'status' => PostStatus::Draft,
    ]);

    $postPlatform = PostPlatform::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $this->socialAccount->id,
        'content' => 'Original content',
    ]);

    $response = $this->actingAs($this->user)->put(route('posts.update', $post), [
        'status' => 'draft',
        'platforms' => [
            [
                'id' => $postPlatform->id,
                'content' => 'Updated content',
                'content_type' => ContentType::LinkedInPost->value,
            ],
        ],
    ]);

    $response->assertRedirect();

    $postPlatform->refresh();
    expect($postPlatform->content)->toBe('Updated content');
});

test('update post cannot update published posts', function () {
    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'status' => PostStatus::Published,
    ]);

    $response = $this->actingAs($this->user)->put(route('posts.update', $post), [
        'status' => 'draft',
    ]);

    $response->assertRedirect();
});

// Destroy tests
test('destroy post requires authentication', function () {
    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    $response = $this->delete(route('posts.destroy', $post));

    $response->assertRedirect(route('login'));
});

test('destroy post deletes the post and redirects back', function () {
    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->from(route('calendar'))
        ->delete(route('posts.destroy', $post));

    $response->assertRedirect(route('calendar'));
    expect(Post::find($post->id))->toBeNull();
});

test('destroy post from status filter redirects back to filter', function (string $status) {
    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->from(route('posts.index', ['status' => $status]))
        ->delete(route('posts.destroy', $post));

    $response->assertRedirect(route('posts.index', ['status' => $status]));
    expect(Post::find($post->id))->toBeNull();
})->with(['draft', 'scheduled', 'published']);

test('destroy post with redirect param redirects to specified route', function () {
    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->delete(route('posts.destroy', $post).'?redirect=posts.index');

    $response->assertRedirect(route('posts.index'));
    expect(Post::find($post->id))->toBeNull();
});

test('destroy post returns 404 for post from different workspace', function () {
    $otherWorkspace = Workspace::factory()->create();
    $post = Post::factory()->create([
        'workspace_id' => $otherWorkspace->id,
        'user_id' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)->delete(route('posts.destroy', $post));

    $response->assertNotFound();
});
