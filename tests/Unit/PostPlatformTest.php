<?php

declare(strict_types=1);

use App\Enums\PostPlatform\Status;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);

    $this->post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    $this->socialAccount = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
    ]);

    $this->postPlatform = PostPlatform::factory()->create([
        'post_id' => $this->post->id,
        'social_account_id' => $this->socialAccount->id,
    ]);
});

test('markAsPublished clears stale error_message and error_context', function () {
    $this->postPlatform->update([
        'status' => Status::Failed,
        'error_message' => 'The post is empty. Please enter a message to share.',
        'error_context' => ['platform_error_code' => 197, 'content_length' => 0],
    ]);

    $this->postPlatform->markAsPublished('platform_post_abc', 'https://www.facebook.com/platform_post_abc');

    $this->postPlatform->refresh();

    expect($this->postPlatform->status)->toBe(Status::Published)
        ->and($this->postPlatform->platform_post_id)->toBe('platform_post_abc')
        ->and($this->postPlatform->error_message)->toBeNull()
        ->and($this->postPlatform->error_context)->toBeNull();
});

test('markAsFailed clears stale platform_post_id and platform_url', function () {
    $this->postPlatform->update([
        'status' => Status::Published,
        'platform_post_id' => 'old_post_id',
        'platform_url' => 'https://www.facebook.com/old_post_id',
        'published_at' => now(),
    ]);

    $this->postPlatform->markAsFailed('Something went wrong.', ['platform_error_code' => 500]);

    $this->postPlatform->refresh();

    expect($this->postPlatform->status)->toBe(Status::Failed)
        ->and($this->postPlatform->error_message)->toBe('Something went wrong.')
        ->and($this->postPlatform->platform_post_id)->toBeNull()
        ->and($this->postPlatform->platform_url)->toBeNull();
});
