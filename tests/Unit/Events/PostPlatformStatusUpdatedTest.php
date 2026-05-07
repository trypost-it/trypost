<?php

declare(strict_types=1);

use App\Enums\Post\Status as PostStatus;
use App\Events\PostPlatformStatusUpdated;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Broadcasting\PrivateChannel;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->socialAccount = SocialAccount::factory()->linkedin()->create([
        'workspace_id' => $this->workspace->id,
    ]);
    $this->post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'status' => PostStatus::Scheduled,
    ]);
    $this->postPlatform = PostPlatform::factory()->linkedin()->create([
        'post_id' => $this->post->id,
        'social_account_id' => $this->socialAccount->id,
    ]);
});

test('event broadcasts on the per-post and per-workspace channels', function () {
    $event = new PostPlatformStatusUpdated($this->postPlatform);
    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(2);
    expect($channels[0])->toBeInstanceOf(PrivateChannel::class);
    expect($channels[0]->name)->toBe('private-post.'.$this->post->id);
    expect($channels[1])->toBeInstanceOf(PrivateChannel::class);
    expect($channels[1]->name)->toBe('private-workspace.'.$this->workspace->id);
});

test('event broadcasts with the post id', function () {
    $event = new PostPlatformStatusUpdated($this->postPlatform);

    expect($event->broadcastWith())->toBe(['post_id' => $this->post->id]);
});

test('event broadcasts as a stable name', function () {
    $event = new PostPlatformStatusUpdated($this->postPlatform);

    expect($event->broadcastAs())->toBe('post.platform.status.updated');
});
