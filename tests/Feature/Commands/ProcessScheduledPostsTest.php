<?php

use App\Console\Commands\ProcessScheduledPosts;
use App\Enums\Post\Status as PostStatus;
use App\Jobs\PublishPost;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
});

test('process scheduled posts dispatches publish job for due posts', function () {
    Queue::fake();

    $socialAccount = SocialAccount::factory()->create(['workspace_id' => $this->workspace->id]);

    $duePost = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'status' => PostStatus::Scheduled,
        'scheduled_at' => now()->subMinute(),
    ]);

    PostPlatform::factory()->create([
        'post_id' => $duePost->id,
        'social_account_id' => $socialAccount->id,
    ]);

    $this->artisan(ProcessScheduledPosts::class)->assertSuccessful();

    Queue::assertPushed(PublishPost::class, function ($job) use ($duePost) {
        return $job->post->id === $duePost->id;
    });
});

test('process scheduled posts does not dispatch for future posts', function () {
    Queue::fake();

    $socialAccount = SocialAccount::factory()->create(['workspace_id' => $this->workspace->id]);

    $futurePost = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'status' => PostStatus::Scheduled,
        'scheduled_at' => now()->addDay(),
    ]);

    PostPlatform::factory()->create([
        'post_id' => $futurePost->id,
        'social_account_id' => $socialAccount->id,
    ]);

    $this->artisan(ProcessScheduledPosts::class)->assertSuccessful();

    Queue::assertNotPushed(PublishPost::class);
});

test('process scheduled posts does not dispatch for draft posts', function () {
    Queue::fake();

    $socialAccount = SocialAccount::factory()->create(['workspace_id' => $this->workspace->id]);

    $draftPost = Post::factory()->draft()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    PostPlatform::factory()->create([
        'post_id' => $draftPost->id,
        'social_account_id' => $socialAccount->id,
    ]);

    $this->artisan(ProcessScheduledPosts::class)->assertSuccessful();

    Queue::assertNotPushed(PublishPost::class);
});

test('process scheduled posts handles multiple due posts', function () {
    Queue::fake();

    $socialAccount = SocialAccount::factory()->create(['workspace_id' => $this->workspace->id]);

    $posts = Post::factory()->count(3)->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'status' => PostStatus::Scheduled,
        'scheduled_at' => now()->subMinute(),
    ]);

    foreach ($posts as $post) {
        PostPlatform::factory()->create([
            'post_id' => $post->id,
            'social_account_id' => $socialAccount->id,
        ]);
    }

    $this->artisan(ProcessScheduledPosts::class)->assertSuccessful();

    Queue::assertPushed(PublishPost::class, 3);
});
