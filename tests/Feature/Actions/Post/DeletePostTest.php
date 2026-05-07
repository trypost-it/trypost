<?php

declare(strict_types=1);

use App\Actions\Post\DeletePost;
use App\Events\PostDeleted;
use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Event;

test('execute deletes the post', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $user->id]);
    $post = Post::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
    ]);

    DeletePost::execute($post);

    expect(Post::find($post->id))->toBeNull();
});

test('execute dispatches PostDeleted with the post and workspace ids', function () {
    Event::fake([PostDeleted::class]);

    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $user->id]);
    $post = Post::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
    ]);

    $postId = $post->id;
    $workspaceId = $post->workspace_id;

    DeletePost::execute($post);

    Event::assertDispatched(
        PostDeleted::class,
        fn (PostDeleted $event) => $event->postId === $postId
            && $event->workspaceId === $workspaceId,
    );
});
