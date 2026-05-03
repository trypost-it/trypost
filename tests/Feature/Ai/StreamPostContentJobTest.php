<?php

declare(strict_types=1);

use App\Ai\Agents\PostContentStreamer;
use App\Jobs\Ai\StreamPostContent;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Bus;

test('job is queued onto the ai queue', function () {
    Bus::fake();

    $workspace = Workspace::factory()->create();
    $user = User::factory()->create();

    StreamPostContent::dispatch(
        workspaceId: $workspace->id,
        userId: $user->id,
        generationId: 'gen-1',
        prompt: 'Write a post about Mondays',
        currentContent: null,
    );

    Bus::assertDispatched(StreamPostContent::class, fn ($job) => $job->queue === 'ai');
});

test('job invokes the PostContentStreamer agent and broadcasts stream events', function () {
    $workspace = Workspace::factory()->create();
    $user = User::factory()->create();

    PostContentStreamer::fake(['Hello world']);

    $job = new StreamPostContent(
        workspaceId: $workspace->id,
        userId: $user->id,
        generationId: 'gen-abc',
        prompt: 'Write a post about Mondays',
        currentContent: null,
    );

    $job->handle();

    PostContentStreamer::assertPrompted('Write a post about Mondays');
});
