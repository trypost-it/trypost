<?php

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

test('event broadcasts on correct channel', function () {
    $event = new PostPlatformStatusUpdated($this->postPlatform);
    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(1);
    expect($channels[0])->toBeInstanceOf(PrivateChannel::class);
    expect($channels[0]->name)->toBe('private-posts.'.$this->post->id);
});

test('event broadcasts with correct data', function () {
    $this->postPlatform->update([
        'status' => 'published',
        'platform_url' => 'https://linkedin.com/post/123',
        'published_at' => now(),
    ]);

    $event = new PostPlatformStatusUpdated($this->postPlatform->fresh());
    $data = $event->broadcastWith();

    expect($data)->toHaveKey('post_platform');
    expect($data)->toHaveKey('post');
    expect($data['post_platform']['id'])->toBe($this->postPlatform->id);
    expect($data['post_platform']['status'])->toBe('published');
    expect($data['post_platform']['platform_url'])->toBe('https://linkedin.com/post/123');
    expect($data['post']['id'])->toBe($this->post->id);
});

test('event broadcasts error message when failed', function () {
    $this->postPlatform->update([
        'status' => 'failed',
        'error_message' => 'API Error',
    ]);

    $event = new PostPlatformStatusUpdated($this->postPlatform->fresh());
    $data = $event->broadcastWith();

    expect($data['post_platform']['error_message'])->toBe('API Error');
});

test('event broadcasts null published_at when not published', function () {
    $event = new PostPlatformStatusUpdated($this->postPlatform);
    $data = $event->broadcastWith();

    expect($data['post_platform']['published_at'])->toBeNull();
});
