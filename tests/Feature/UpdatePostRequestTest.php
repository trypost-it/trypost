<?php

declare(strict_types=1);

use App\Enums\Post\Status;
use App\Enums\PostPlatform\ContentType;
use App\Enums\SocialAccount\Platform;
use App\Enums\UserWorkspace\Role;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Member->value]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);

    $this->post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    // Media payload used by tests that need to satisfy ContentTypeCompatibleWithMedia.
    $this->mediaPayload = [
        [
            'id' => 'test-media-video',
            'path' => 'media/2026-01/test-video.mp4',
            'url' => 'https://example.com/media/2026-01/test-video.mp4',
            'type' => 'video',
            'mime_type' => 'video/mp4',
            'original_filename' => 'test-video.mp4',
        ],
    ];
    $this->socialAccount = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::TikTok,
    ]);
    $this->postPlatform = PostPlatform::factory()->tiktok()->create([
        'post_id' => $this->post->id,
        'social_account_id' => $this->socialAccount->id,
        // Override factory default so we control privacy_level per test.
        'meta' => [],
    ]);
});

test('publishing a tiktok post without privacy_level is rejected', function () {
    $response = $this->actingAs($this->user)
        ->put(route('app.posts.update', $this->post), [
            'status' => Status::Publishing->value,
            'media' => $this->mediaPayload,
            'platforms' => [
                [
                    'id' => $this->postPlatform->id,
                    'content_type' => ContentType::TikTokVideo->value,
                    'meta' => [],
                ],
            ],
        ]);

    $response->assertSessionHasErrors('platforms.0.meta.privacy_level');
});

test('publishing a tiktok post with privacy_level passes privacy_level validation', function () {
    $response = $this->actingAs($this->user)
        ->put(route('app.posts.update', $this->post), [
            'status' => Status::Publishing->value,
            'media' => $this->mediaPayload,
            'platforms' => [
                [
                    'id' => $this->postPlatform->id,
                    'content_type' => ContentType::TikTokVideo->value,
                    'meta' => ['privacy_level' => 'SELF_ONLY'],
                ],
            ],
        ]);

    $response->assertSessionDoesntHaveErrors(['platforms.0.meta.privacy_level']);
});

test('saving a tiktok post as draft without privacy_level skips the privacy_level rule', function () {
    $response = $this->actingAs($this->user)
        ->put(route('app.posts.update', $this->post), [
            'status' => Status::Draft->value,
            'platforms' => [
                [
                    'id' => $this->postPlatform->id,
                    'content_type' => ContentType::TikTokVideo->value,
                    'meta' => [],
                ],
            ],
        ]);

    $response->assertSessionDoesntHaveErrors(['platforms.0.meta.privacy_level']);
});
