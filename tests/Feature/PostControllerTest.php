<?php

declare(strict_types=1);

use App\Enums\Post\Status as PostStatus;
use App\Enums\PostPlatform\ContentType;
use App\Enums\SocialAccount\Platform;
use App\Enums\User\Setup;
use App\Enums\UserWorkspace\Role;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceLabel;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->user = User::factory()->create(['setup' => Setup::Completed]);
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Owner->value]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);

    $this->socialAccount = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::LinkedIn,
    ]);
});

// Index tests
test('posts index requires authentication', function () {
    $response = $this->get(route('app.posts.index'));

    $response->assertRedirect(route('login'));
});

test('posts index shows posts for current workspace', function () {
    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)->get(route('app.posts.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('posts/Index', false)
        ->has('posts.data', 1)
    );
});

test('posts index redirects to create workspace if no workspace', function () {
    $this->user->update(['current_workspace_id' => null]);

    $response = $this->actingAs($this->user)->get(route('app.posts.index'));

    $response->assertRedirect(route('app.workspaces.create'));
});

// Calendar tests
test('calendar requires authentication', function () {
    $response = $this->get(route('app.calendar'));

    $response->assertRedirect(route('login'));
});

test('calendar shows posts for current week', function () {
    $response = $this->actingAs($this->user)->get(route('app.calendar'));

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
    $response = $this->actingAs($this->user)->get(route('app.calendar', ['view' => 'month']));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('view', 'month')
    );
});

// Store tests
test('store post requires authentication', function () {
    $response = $this->post(route('app.posts.store'));

    $response->assertRedirect(route('login'));
});

test('store post redirects to accounts if no social accounts connected', function () {
    $this->socialAccount->delete();

    $response = $this->actingAs($this->user)->post(route('app.posts.store'));

    $response->assertRedirect(route('app.accounts'));
});

test('store post creates draft and redirects to edit', function () {
    $response = $this->actingAs($this->user)->post(route('app.posts.store'));

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

    $response = $this->get(route('app.posts.edit', $post));

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

    $response = $this->actingAs($this->user)->get(route('app.posts.edit', $post));

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

    $response = $this->actingAs($this->user)->get(route('app.posts.edit', $post));

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

    $response = $this->actingAs($this->user)->get(route('app.posts.edit', $post));

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

    $response = $this->put(route('app.posts.update', $post), []);

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

    $response = $this->actingAs($this->user)->put(route('app.posts.update', $post), [
        'status' => 'draft',
        'synced' => true,
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
    expect($postPlatform->content_type)->toBe(ContentType::LinkedInPost);
});

test('update post cannot update published posts', function () {
    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'status' => PostStatus::Published,
    ]);

    $postPlatform = PostPlatform::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $this->socialAccount->id,
    ]);

    $response = $this->actingAs($this->user)->put(route('app.posts.update', $post), [
        'status' => 'draft',
        'synced' => true,
        'platforms' => [
            [
                'id' => $postPlatform->id,
                'content' => 'Test content',
                'content_type' => ContentType::LinkedInPost->value,
            ],
        ],
    ]);

    $response->assertRedirect();
});

test('publish now updates scheduled_at to current time', function () {
    Mail::fake();
    $this->freezeTime();

    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'status' => PostStatus::Draft,
        'scheduled_at' => now()->addDays(7),
    ]);

    $postPlatform = PostPlatform::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $this->socialAccount->id,
        'content' => 'Test content',
    ]);

    $response = $this->actingAs($this->user)->put(route('app.posts.update', $post), [
        'status' => 'publishing',
        'synced' => true,
        'platforms' => [
            [
                'id' => $postPlatform->id,
                'content' => 'Test content',
                'content_type' => ContentType::LinkedInPost->value,
            ],
        ],
    ]);

    $response->assertRedirect();

    $post->refresh();
    expect($post->scheduled_at->toDateTimeString())->toBe(now()->toDateTimeString());
});

// Destroy tests
test('destroy post requires authentication', function () {
    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    $response = $this->delete(route('app.posts.destroy', $post));

    $response->assertRedirect(route('login'));
});

test('destroy post deletes the post and redirects back', function () {
    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->from(route('app.calendar'))
        ->delete(route('app.posts.destroy', $post));

    $response->assertRedirect(route('app.calendar'));
    expect(Post::find($post->id))->toBeNull();
});

test('destroy post from status filter redirects back to filter', function (string $status) {
    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->from(route('app.posts.index', ['status' => $status]))
        ->delete(route('app.posts.destroy', $post));

    $response->assertRedirect(route('app.posts.index', ['status' => $status]));
    expect(Post::find($post->id))->toBeNull();
})->with(['draft', 'scheduled', 'published']);

test('destroy post with redirect param redirects to specified route', function () {
    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->delete(route('app.posts.destroy', $post).'?redirect=app.posts.index');

    $response->assertRedirect(route('app.posts.index'));
    expect(Post::find($post->id))->toBeNull();
});

test('destroy post returns 404 for post from different workspace', function () {
    $otherWorkspace = Workspace::factory()->create();
    $post = Post::factory()->create([
        'workspace_id' => $otherWorkspace->id,
        'user_id' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)->delete(route('app.posts.destroy', $post));

    $response->assertNotFound();
});

// Label tests
test('edit post includes workspace labels', function () {
    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'status' => PostStatus::Draft,
    ]);

    PostPlatform::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $this->socialAccount->id,
    ]);

    $label = WorkspaceLabel::factory()->create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Marketing',
        'color' => '#FF0000',
    ]);

    $response = $this->actingAs($this->user)->get(route('app.posts.edit', $post));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('posts/Edit')
        ->has('labels', 1)
        ->where('labels.0.name', 'Marketing')
    );
});

test('update post can attach labels', function () {
    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'status' => PostStatus::Draft,
    ]);

    $postPlatform = PostPlatform::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $this->socialAccount->id,
    ]);

    $label = WorkspaceLabel::factory()->create([
        'workspace_id' => $this->workspace->id,
    ]);

    $response = $this->actingAs($this->user)->put(route('app.posts.update', $post), [
        'status' => 'draft',
        'synced' => true,
        'platforms' => [
            [
                'id' => $postPlatform->id,
                'content' => 'Test content',
                'content_type' => ContentType::LinkedInPost->value,
            ],
        ],
        'label_ids' => [$label->id],
    ]);

    $response->assertRedirect();

    $post->refresh();
    expect($post->labels)->toHaveCount(1);
    expect($post->labels->first()->id)->toBe($label->id);
});

test('update post can detach labels', function () {
    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'status' => PostStatus::Draft,
    ]);

    $postPlatform = PostPlatform::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $this->socialAccount->id,
    ]);

    $label = WorkspaceLabel::factory()->create([
        'workspace_id' => $this->workspace->id,
    ]);

    $post->labels()->attach($label);

    $response = $this->actingAs($this->user)->put(route('app.posts.update', $post), [
        'status' => 'draft',
        'synced' => true,
        'platforms' => [
            [
                'id' => $postPlatform->id,
                'content' => 'Test content',
                'content_type' => ContentType::LinkedInPost->value,
            ],
        ],
        'label_ids' => [],
    ]);

    $response->assertRedirect();

    $post->refresh();
    expect($post->labels)->toHaveCount(0);
});

test('update post can sync multiple labels', function () {
    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'status' => PostStatus::Draft,
    ]);

    $postPlatform = PostPlatform::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $this->socialAccount->id,
    ]);

    $label1 = WorkspaceLabel::factory()->create(['workspace_id' => $this->workspace->id]);
    $label2 = WorkspaceLabel::factory()->create(['workspace_id' => $this->workspace->id]);
    $label3 = WorkspaceLabel::factory()->create(['workspace_id' => $this->workspace->id]);

    // Attach initial label
    $post->labels()->attach($label1);

    // Update with different labels
    $response = $this->actingAs($this->user)->put(route('app.posts.update', $post), [
        'status' => 'draft',
        'synced' => true,
        'platforms' => [
            [
                'id' => $postPlatform->id,
                'content' => 'Test content',
                'content_type' => ContentType::LinkedInPost->value,
            ],
        ],
        'label_ids' => [$label2->id, $label3->id],
    ]);

    $response->assertRedirect();

    $post->refresh();
    expect($post->labels)->toHaveCount(2);
    expect($post->labels->pluck('id')->toArray())->toEqualCanonicalizing([$label2->id, $label3->id]);
});

test('update post validates label_ids exist', function () {
    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'status' => PostStatus::Draft,
    ]);

    $postPlatform = PostPlatform::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $this->socialAccount->id,
    ]);

    $response = $this->actingAs($this->user)->put(route('app.posts.update', $post), [
        'status' => 'draft',
        'synced' => true,
        'platforms' => [
            [
                'id' => $postPlatform->id,
                'content' => 'Test content',
                'content_type' => ContentType::LinkedInPost->value,
            ],
        ],
        'label_ids' => ['non-existent-uuid'],
    ]);

    $response->assertSessionHasErrors('label_ids.0');
});

// Member authorization tests
test('member can view posts index', function () {
    $member = User::factory()->create(['setup' => Setup::Completed]);
    $this->workspace->members()->attach($member->id, ['role' => Role::Member->value]);
    $member->update(['current_workspace_id' => $this->workspace->id]);

    $response = $this->actingAs($member)->get(route('app.posts.index'));

    $response->assertOk();
});

test('member can create post', function () {
    $member = User::factory()->create(['setup' => Setup::Completed]);
    $this->workspace->members()->attach($member->id, ['role' => Role::Member->value]);
    $member->update(['current_workspace_id' => $this->workspace->id]);

    $response = $this->actingAs($member)->post(route('app.posts.store'));

    $response->assertRedirect();
});
