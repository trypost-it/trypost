<?php

declare(strict_types=1);

use App\Enums\Post\Status as PostStatus;
use App\Enums\PostPlatform\Status as PlatformStatus;
use App\Enums\SocialAccount\Platform;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->socialAccount = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::LinkedIn,
    ]);
});

test('it recovers posts stuck in publishing for over 1 hour', function () {
    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'status' => PostStatus::Publishing,
        'updated_at' => now()->subHours(2),
    ]);

    $platform = PostPlatform::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $this->socialAccount->id,
        'status' => PlatformStatus::Publishing,
        'enabled' => true,
        'updated_at' => now()->subHours(2),
    ]);

    $this->artisan('social:recover-stuck-posts')->assertSuccessful();

    $platform->refresh();
    $post->refresh();

    expect($platform->status)->toBe(PlatformStatus::Failed);
    expect($platform->error_message)->toBe('Publishing timed out. Please try again.');
    expect($post->status)->toBe(PostStatus::Failed);
});

test('it does not touch posts publishing for less than 1 hour', function () {
    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'status' => PostStatus::Publishing,
        'updated_at' => now()->subMinutes(30),
    ]);

    $platform = PostPlatform::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $this->socialAccount->id,
        'status' => PlatformStatus::Publishing,
        'enabled' => true,
        'updated_at' => now()->subMinutes(30),
    ]);

    $this->artisan('social:recover-stuck-posts')->assertSuccessful();

    $platform->refresh();
    expect($platform->status)->toBe(PlatformStatus::Publishing);
});

test('it marks post as partially published when some platforms succeeded', function () {
    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'status' => PostStatus::Publishing,
        'updated_at' => now()->subHours(2),
    ]);

    // One succeeded
    PostPlatform::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $this->socialAccount->id,
        'status' => PlatformStatus::Published,
        'enabled' => true,
    ]);

    // One stuck
    $stuckPlatform = PostPlatform::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => SocialAccount::factory()->create([
            'workspace_id' => $this->workspace->id,
            'platform' => Platform::Instagram,
        ])->id,
        'status' => PlatformStatus::Publishing,
        'enabled' => true,
        'updated_at' => now()->subHours(2),
    ]);

    $this->artisan('social:recover-stuck-posts')->assertSuccessful();

    $stuckPlatform->refresh();
    $post->refresh();

    expect($stuckPlatform->status)->toBe(PlatformStatus::Failed);
    expect($post->status)->toBe(PostStatus::PartiallyPublished);
});
