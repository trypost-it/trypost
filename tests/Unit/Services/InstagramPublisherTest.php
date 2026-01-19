<?php

use App\Enums\PostPlatform\ContentType;
use App\Enums\SocialAccount\Platform;
use App\Exceptions\TokenExpiredException;
use App\Models\Media;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\Workspace;
use App\Services\Social\InstagramPublisher;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->workspace = Workspace::factory()->create();
    $this->socialAccount = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::Instagram,
        'platform_user_id' => '12345678',
        'access_token' => 'test-token',
    ]);
    $this->post = Post::factory()->create(['workspace_id' => $this->workspace->id]);
    $this->postPlatform = PostPlatform::factory()->instagram()->create([
        'post_id' => $this->post->id,
        'social_account_id' => $this->socialAccount->id,
        'content' => 'Test caption',
        'content_type' => ContentType::InstagramFeed,
    ]);
});

test('instagram publisher throws exception when no media', function () {
    $publisher = new InstagramPublisher;

    expect(fn () => $publisher->publish($this->postPlatform))
        ->toThrow(\Exception::class, 'Instagram requires at least one image or video.');
});

test('instagram publisher publishes single image', function () {
    Http::fake([
        '*/12345678/media' => Http::response(['id' => 'container-123'], 200),
        '*/container-123*' => Http::response(['status_code' => 'FINISHED'], 200),
        '*/12345678/media_publish' => Http::response(['id' => 'post-123'], 200),
        '*/post-123*' => Http::response(['permalink' => 'https://instagram.com/p/abc123'], 200),
    ]);

    Media::factory()->create([
        'mediable_type' => 'postPlatform',
        'mediable_id' => $this->postPlatform->id,
        'mime_type' => 'image/jpeg',
    ]);

    $publisher = new InstagramPublisher;
    $result = $publisher->publish($this->postPlatform);

    expect($result['id'])->toBe('post-123');
    expect($result['url'])->toBe('https://instagram.com/p/abc123');
});

test('instagram publisher publishes reel', function () {
    $this->postPlatform->update(['content_type' => ContentType::InstagramReel]);

    Http::fake([
        '*/12345678/media' => Http::response(['id' => 'container-123'], 200),
        '*/container-123*' => Http::response(['status_code' => 'FINISHED'], 200),
        '*/12345678/media_publish' => Http::response(['id' => 'reel-123'], 200),
        '*/reel-123*' => Http::response(['permalink' => 'https://instagram.com/reel/abc123'], 200),
    ]);

    Media::factory()->create([
        'mediable_type' => 'postPlatform',
        'mediable_id' => $this->postPlatform->id,
        'mime_type' => 'video/mp4',
    ]);

    $publisher = new InstagramPublisher;
    $result = $publisher->publish($this->postPlatform);

    expect($result['id'])->toBe('reel-123');
});

test('instagram publisher publishes story', function () {
    $this->postPlatform->update(['content_type' => ContentType::InstagramStory]);

    Http::fake([
        '*/12345678/media' => Http::response(['id' => 'container-123'], 200),
        '*/container-123*' => Http::response(['status_code' => 'FINISHED'], 200),
        '*/12345678/media_publish' => Http::response(['id' => 'story-123'], 200),
        '*/story-123*' => Http::response(['permalink' => 'https://instagram.com/stories/abc123'], 200),
    ]);

    Media::factory()->create([
        'mediable_type' => 'postPlatform',
        'mediable_id' => $this->postPlatform->id,
        'mime_type' => 'image/jpeg',
    ]);

    $publisher = new InstagramPublisher;
    $result = $publisher->publish($this->postPlatform);

    expect($result['id'])->toBe('story-123');
});

test('instagram publisher throws token expired exception on oauth error', function () {
    Http::fake([
        '*' => Http::response([
            'error' => [
                'type' => 'OAuthException',
                'code' => 190,
                'message' => 'Invalid OAuth access token',
            ],
        ], 400),
    ]);

    Media::factory()->create([
        'mediable_type' => 'postPlatform',
        'mediable_id' => $this->postPlatform->id,
        'mime_type' => 'image/jpeg',
    ]);

    $publisher = new InstagramPublisher;

    expect(fn () => $publisher->publish($this->postPlatform))
        ->toThrow(TokenExpiredException::class);
});

test('instagram publisher throws exception on api error', function () {
    Http::fake([
        '*' => Http::response([
            'error' => [
                'code' => 100,
                'message' => 'Invalid parameter',
            ],
        ], 400),
    ]);

    Media::factory()->create([
        'mediable_type' => 'postPlatform',
        'mediable_id' => $this->postPlatform->id,
        'mime_type' => 'image/jpeg',
    ]);

    $publisher = new InstagramPublisher;

    expect(fn () => $publisher->publish($this->postPlatform))
        ->toThrow(\Exception::class);
});
