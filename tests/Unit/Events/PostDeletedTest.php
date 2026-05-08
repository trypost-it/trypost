<?php

declare(strict_types=1);

use App\Events\PostDeleted;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Str;

test('event broadcasts on the workspace channel only', function () {
    $event = new PostDeleted(Str::uuid()->toString(), 'workspace-123');
    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(1);
    expect($channels[0])->toBeInstanceOf(PrivateChannel::class);
    expect($channels[0]->name)->toBe('private-workspace.workspace-123');
});

test('event broadcasts with the post id', function () {
    $postId = (string) Str::uuid();
    $event = new PostDeleted($postId, 'workspace-123');

    expect($event->broadcastWith())->toBe(['post_id' => $postId]);
});

test('event broadcasts as a stable name', function () {
    $event = new PostDeleted(Str::uuid()->toString(), 'workspace-123');

    expect($event->broadcastAs())->toBe('post.deleted');
});
