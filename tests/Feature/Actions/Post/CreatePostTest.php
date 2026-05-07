<?php

declare(strict_types=1);

use App\Actions\Post\CreatePost;
use App\Events\PostCreated;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Event;

test('execute dispatches PostCreated with the persisted post', function () {
    Event::fake([PostCreated::class]);

    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $user->id]);

    $post = CreatePost::execute($workspace, $user, [
        'content' => 'Hello world',
    ]);

    Event::assertDispatched(
        PostCreated::class,
        fn (PostCreated $event) => $event->post->id === $post->id
            && $event->post->workspace_id === $workspace->id,
    );
});
