<?php

declare(strict_types=1);

use App\Enums\UserWorkspace\Role;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    $this->user = User::factory()->create([]);
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Member->value]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);

    $this->post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);
});

test('index returns paginated comments with replies', function () {
    $parent = PostComment::factory()->create([
        'post_id' => $this->post->id,
        'user_id' => $this->user->id,
    ]);

    $reply = PostComment::factory()->reply($parent)->create([
        'user_id' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson(route('app.posts.comments.index', $this->post));

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.id', $parent->id);
    $response->assertJsonCount(1, 'data.0.replies');
    $response->assertJsonPath('data.0.replies.0.id', $reply->id);
});

test('store creates a comment', function () {
    $response = $this->actingAs($this->user)
        ->postJson(route('app.posts.comments.store', $this->post), [
            'body' => 'This is a comment.',
        ]);

    $response->assertCreated();
    $response->assertJsonPath('body', 'This is a comment.');

    $this->assertDatabaseHas('post_comments', [
        'post_id' => $this->post->id,
        'user_id' => $this->user->id,
        'body' => 'This is a comment.',
        'parent_id' => null,
    ]);
});

test('store creates a reply', function () {
    $parent = PostComment::factory()->create([
        'post_id' => $this->post->id,
        'user_id' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson(route('app.posts.comments.store', $this->post), [
            'body' => 'This is a reply.',
            'parent_id' => $parent->id,
        ]);

    $response->assertCreated();
    $response->assertJsonPath('parent_id', $parent->id);

    $this->assertDatabaseHas('post_comments', [
        'post_id' => $this->post->id,
        'parent_id' => $parent->id,
        'body' => 'This is a reply.',
    ]);
});

test('store rejects reply to a reply', function () {
    $parent = PostComment::factory()->create([
        'post_id' => $this->post->id,
        'user_id' => $this->user->id,
    ]);

    $reply = PostComment::factory()->reply($parent)->create([
        'user_id' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson(route('app.posts.comments.store', $this->post), [
            'body' => 'Nested reply attempt.',
            'parent_id' => $reply->id,
        ]);

    $response->assertStatus(422);
});

test('update own comment', function () {
    $comment = PostComment::factory()->create([
        'post_id' => $this->post->id,
        'user_id' => $this->user->id,
        'body' => 'Original body.',
    ]);

    $response = $this->actingAs($this->user)
        ->putJson(route('app.posts.comments.update', [$this->post, $comment]), [
            'body' => 'Updated body.',
        ]);

    $response->assertOk();
    $response->assertJsonPath('body', 'Updated body.');

    $this->assertDatabaseHas('post_comments', [
        'id' => $comment->id,
        'body' => 'Updated body.',
    ]);
});

test('cannot update other user comment', function () {
    $otherUser = User::factory()->create([]);
    $this->workspace->members()->attach($otherUser->id, ['role' => Role::Member->value]);
    $otherUser->update(['current_workspace_id' => $this->workspace->id]);

    $comment = PostComment::factory()->create([
        'post_id' => $this->post->id,
        'user_id' => $this->user->id,
        'body' => 'Original body.',
    ]);

    $response = $this->actingAs($otherUser)
        ->putJson(route('app.posts.comments.update', [$this->post, $comment]), [
            'body' => 'Hacked body.',
        ]);

    $response->assertForbidden();
});

test('delete own comment', function () {
    $comment = PostComment::factory()->create([
        'post_id' => $this->post->id,
        'user_id' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->deleteJson(route('app.posts.comments.destroy', [$this->post, $comment]));

    $response->assertNoContent();

    $this->assertDatabaseMissing('post_comments', [
        'id' => $comment->id,
    ]);
});

test('cannot delete other user comment', function () {
    $otherUser = User::factory()->create([]);
    $this->workspace->members()->attach($otherUser->id, ['role' => Role::Member->value]);
    $otherUser->update(['current_workspace_id' => $this->workspace->id]);

    $comment = PostComment::factory()->create([
        'post_id' => $this->post->id,
        'user_id' => $this->user->id,
    ]);

    $response = $this->actingAs($otherUser)
        ->deleteJson(route('app.posts.comments.destroy', [$this->post, $comment]));

    $response->assertForbidden();
});

test('react toggles emoji', function () {
    $comment = PostComment::factory()->create([
        'post_id' => $this->post->id,
        'user_id' => $this->user->id,
    ]);

    // First reaction adds the emoji
    $response = $this->actingAs($this->user)
        ->postJson(route('app.posts.comments.react', [$this->post, $comment]), [
            'emoji' => '👍',
        ]);

    $response->assertOk();
    $comment->refresh();
    expect($comment->reactions)->toHaveCount(1);
    expect($comment->reactions[0]['emoji'])->toBe('👍');
    expect($comment->reactions[0]['user_id'])->toBe($this->user->id);

    // Same reaction again removes the emoji
    $response = $this->actingAs($this->user)
        ->postJson(route('app.posts.comments.react', [$this->post, $comment]), [
            'emoji' => '👍',
        ]);

    $response->assertOk();
    $comment->refresh();
    expect($comment->reactions)->toHaveCount(0);
});

test('cannot comment on post from other workspace', function () {
    $otherUser = User::factory()->create([]);
    $otherWorkspace = Workspace::factory()->create(['user_id' => $otherUser->id]);
    $otherWorkspace->members()->attach($otherUser->id, ['role' => Role::Member->value]);
    $otherUser->update(['current_workspace_id' => $otherWorkspace->id]);

    $response = $this->actingAs($otherUser)
        ->postJson(route('app.posts.comments.store', $this->post), [
            'body' => 'Cross-workspace comment.',
        ]);

    $response->assertForbidden();
});
