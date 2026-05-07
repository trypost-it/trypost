<?php

declare(strict_types=1);

use App\Events\PostCreated;
use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Broadcasting\PrivateChannel;

test('event broadcasts on the workspace channel', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $user->id]);
    $post = Post::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
    ]);

    $event = new PostCreated($post);
    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(1);
    expect($channels[0])->toBeInstanceOf(PrivateChannel::class);
    expect($channels[0]->name)->toBe('private-workspace.'.$workspace->id);
});

test('event broadcasts with the post id', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $user->id]);
    $post = Post::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
    ]);

    $event = new PostCreated($post);

    expect($event->broadcastWith())->toBe(['post_id' => $post->id]);
});

test('event broadcasts as a stable name', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $user->id]);
    $post = Post::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
    ]);

    $event = new PostCreated($post);

    expect($event->broadcastAs())->toBe('post.created');
});
