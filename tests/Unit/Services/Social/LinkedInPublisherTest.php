<?php

declare(strict_types=1);

use App\Enums\PostPlatform\ContentType;
use App\Enums\SocialAccount\Platform;
use App\Exceptions\TokenExpiredException;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Social\LinkedInPublisher;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);

    $this->socialAccount = SocialAccount::factory()->linkedin()->create([
        'workspace_id' => $this->workspace->id,
        'platform_user_id' => 'abc123xyz',
        'username' => 'johndoe',
        'token_expires_at' => now()->addDays(60),
    ]);

    $this->post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    $this->postPlatform = PostPlatform::factory()->create([
        'post_id' => $this->post->id,
        'social_account_id' => $this->socialAccount->id,
        'platform' => Platform::LinkedIn,
        'content_type' => ContentType::LinkedInPost,
        'content' => 'Hello from LinkedIn!',
    ]);

    $this->publisher = new LinkedInPublisher;
});

test('linkedin publisher can publish text-only post', function () {
    Http::fake([
        'https://api.linkedin.com/rest/posts' => Http::response(null, 201, [
            'x-restli-id' => 'urn:li:share:1234567890',
        ]),
    ]);

    $result = $this->publisher->publish($this->postPlatform);

    expect($result)->toHaveKey('id');
    expect($result)->toHaveKey('url');
    expect($result['id'])->toBe('urn:li:share:1234567890');
    expect($result['url'])->toContain('linkedin.com/feed/update/urn:li:share:1234567890');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/rest/posts')
            && $request['author'] === 'urn:li:person:abc123xyz'
            && $request['commentary'] === 'Hello from LinkedIn!'
            && $request['visibility'] === 'PUBLIC';
    });
});

test('linkedin publisher uses correct headers', function () {
    Http::fake([
        'https://api.linkedin.com/rest/posts' => Http::response(null, 201, [
            'x-restli-id' => 'urn:li:share:1234567890',
        ]),
    ]);

    $this->publisher->publish($this->postPlatform);

    Http::assertSent(function ($request) {
        return $request->hasHeader('Authorization')
            && $request->hasHeader('X-Restli-Protocol-Version')
            && $request->hasHeader('LinkedIn-Version')
            && str_starts_with($request->header('Authorization')[0], 'Bearer ');
    });
});

test('linkedin publisher throws exception on api error', function () {
    Http::fake([
        'https://api.linkedin.com/rest/posts' => Http::response([
            'message' => 'Invalid request',
            'status' => 400,
        ], 400),
    ]);

    expect(fn () => $this->publisher->publish($this->postPlatform))
        ->toThrow(Exception::class);
});

test('linkedin publisher throws token expired exception on auth error after retry', function () {
    Http::fake([
        'https://api.linkedin.com/rest/posts' => Http::response([
            'code' => 'EXPIRED_ACCESS_TOKEN',
            'message' => 'The token used in the request has expired',
        ], 401),
        'https://www.linkedin.com/oauth/v2/accessToken' => Http::response([
            'error' => 'invalid_grant',
            'error_description' => 'The refresh token is invalid',
        ], 400),
    ]);

    expect(fn () => $this->publisher->publish($this->postPlatform))
        ->toThrow(TokenExpiredException::class);
});

test('linkedin publisher refreshes token when expired', function () {
    $this->socialAccount->update(['token_expires_at' => now()->subHour()]);

    Http::fake([
        'https://www.linkedin.com/oauth/v2/accessToken' => Http::response([
            'access_token' => 'new-access-token',
            'refresh_token' => 'new-refresh-token',
            'expires_in' => 5184000,
        ], 200),
        'https://api.linkedin.com/rest/posts' => Http::response(null, 201, [
            'x-restli-id' => 'urn:li:share:1234567890',
        ]),
    ]);

    $this->publisher->publish($this->postPlatform);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'oauth/v2/accessToken');
    });

    $this->socialAccount->refresh();
    expect($this->socialAccount->access_token)->toBe('new-access-token');
});

test('linkedin publisher throws exception when no refresh token available', function () {
    $this->socialAccount->update([
        'token_expires_at' => now()->subHour(),
        'refresh_token' => null,
    ]);

    expect(fn () => $this->publisher->publish($this->postPlatform))
        ->toThrow(TokenExpiredException::class, 'No refresh token available for LinkedIn account');
});

test('linkedin publisher handles empty content', function () {
    $this->postPlatform->update(['content' => '']);

    Http::fake([
        'https://api.linkedin.com/rest/posts' => Http::response(null, 201, [
            'x-restli-id' => 'urn:li:share:1234567890',
        ]),
    ]);

    $result = $this->publisher->publish($this->postPlatform);

    expect($result['id'])->toBe('urn:li:share:1234567890');

    Http::assertSent(function ($request) {
        return $request['commentary'] === '';
    });
});

test('linkedin publisher throws exception for unsupported content type', function () {
    $this->postPlatform->update(['content_type' => ContentType::InstagramFeed]);

    expect(fn () => $this->publisher->publish($this->postPlatform))
        ->toThrow(Exception::class, 'Unsupported LinkedIn content type');
});

test('linkedin publisher can publish post with image', function () {
    $this->postPlatform->media()->create([
        'collection' => 'default',
        'type' => 'image',
        'path' => 'media/2026-01/test-image.jpg',
        'original_filename' => 'test.jpg',
        'mime_type' => 'image/jpeg',
        'size' => 512000,
        'order' => 0,
        'meta' => ['width' => 1920, 'height' => 1080],
    ]);

    $uploadUrl = 'https://www.linkedin.com/dms/upload/v2/pic/0/C5622AQFake';

    Http::fake(function ($request) use ($uploadUrl) {
        $url = $request->url();

        if (str_contains($url, '/rest/images')) {
            return Http::response([
                'value' => [
                    'uploadUrl' => $uploadUrl,
                    'image' => 'urn:li:image:C5622AQFakeImageUrn',
                ],
            ], 200);
        }

        if ($url === $uploadUrl) {
            return Http::response(null, 201);
        }

        if (str_contains($url, '/rest/posts')) {
            return Http::response(null, 201, ['x-restli-id' => 'urn:li:share:9876543210']);
        }

        // Media download fallback
        return Http::response('fake-image-content', 200);
    });

    $result = $this->publisher->publish($this->postPlatform);

    expect($result['id'])->toBe('urn:li:share:9876543210');
    expect($result['url'])->toContain('linkedin.com/feed/update/urn:li:share:9876543210');

    Http::assertSent(fn ($request) => str_contains($request->url(), '/rest/images'));
    Http::assertSent(fn ($request) => str_contains($request->url(), '/rest/posts')
        && isset($request['content']['media']['id'])
    );
});

test('linkedin publisher can publish carousel with multiple images', function () {
    $this->postPlatform->update(['content_type' => ContentType::LinkedInCarousel]);

    for ($i = 1; $i <= 3; $i++) {
        $this->postPlatform->media()->create([
            'collection' => 'default',
            'type' => 'image',
            'path' => "media/2026-01/carousel-{$i}.jpg",
            'original_filename' => "carousel-{$i}.jpg",
            'mime_type' => 'image/jpeg',
            'size' => 256000,
            'order' => $i - 1,
            'meta' => ['width' => 1200, 'height' => 628],
        ]);
    }

    $uploadUrls = [
        'https://www.linkedin.com/dms/upload/v2/pic/carousel/1',
        'https://www.linkedin.com/dms/upload/v2/pic/carousel/2',
        'https://www.linkedin.com/dms/upload/v2/pic/carousel/3',
    ];

    $imageUrns = [
        'urn:li:image:CarouselImageUrn1',
        'urn:li:image:CarouselImageUrn2',
        'urn:li:image:CarouselImageUrn3',
    ];

    $initCallCount = 0;

    Http::fake(function ($request) use ($uploadUrls, $imageUrns, &$initCallCount) {
        $url = $request->url();

        if (str_contains($url, '/rest/images')) {
            $idx = $initCallCount % 3;
            $initCallCount++;

            return Http::response([
                'value' => [
                    'uploadUrl' => $uploadUrls[$idx],
                    'image' => $imageUrns[$idx],
                ],
            ], 200);
        }

        // Image PUT upload
        foreach ($uploadUrls as $uploadUrl) {
            if ($url === $uploadUrl) {
                return Http::response(null, 201);
            }
        }

        if (str_contains($url, '/rest/posts')) {
            return Http::response(null, 201, ['x-restli-id' => 'urn:li:share:carousel999']);
        }

        // Media download fallback
        return Http::response('fake-image-content', 200);
    });

    $result = $this->publisher->publish($this->postPlatform);

    expect($result['id'])->toBe('urn:li:share:carousel999');
    expect($result['url'])->toContain('linkedin.com/feed/update/urn:li:share:carousel999');

    // Assert the post payload contains multiImage.images with 3 items
    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/rest/posts')) {
            return false;
        }
        $images = data_get($request->data(), 'content.multiImage.images');

        return is_array($images) && count($images) === 3;
    });
});

test('linkedin publisher can publish post with video', function () {
    $this->postPlatform->media()->create([
        'collection' => 'default',
        'type' => 'video',
        'path' => 'media/2026-01/test-video.mp4',
        'original_filename' => 'test-video.mp4',
        'mime_type' => 'video/mp4',
        'size' => 2 * 1024 * 1024,
        'order' => 0,
        'meta' => ['duration' => 30],
    ]);

    $chunkUploadUrl = 'https://www.linkedin.com/dms/upload/v2/chunk/video/1';

    Http::fake(function ($request) use ($chunkUploadUrl) {
        $url = $request->url();

        if (str_contains($url, 'initializeUpload') && str_contains($url, '/rest/videos')) {
            return Http::response([
                'value' => [
                    'video' => 'urn:li:video:FakeVideoUrn',
                    'uploadToken' => 'upload-token-abc',
                    'uploadInstructions' => [
                        [
                            'uploadUrl' => $chunkUploadUrl,
                            'firstByte' => 0,
                            'lastByte' => 1023,
                        ],
                    ],
                ],
            ], 200);
        }

        if ($url === $chunkUploadUrl) {
            return Http::response(null, 200, ['etag' => '"etag-abc123"']);
        }

        if (str_contains($url, 'finalizeUpload') && str_contains($url, '/rest/videos')) {
            return Http::response(null, 200);
        }

        if (str_contains($url, '/rest/videos/')) {
            return Http::response(['status' => 'AVAILABLE'], 200);
        }

        if (str_contains($url, '/rest/posts')) {
            return Http::response(null, 201, ['x-restli-id' => 'urn:li:share:1111111111']);
        }

        // Media download fallback
        return Http::response(str_repeat('x', 1024), 200);
    });

    $result = $this->publisher->publish($this->postPlatform);

    expect($result['id'])->toBe('urn:li:share:1111111111');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'initializeUpload'));
    Http::assertSent(fn ($request) => str_contains($request->url(), 'finalizeUpload'));
    Http::assertSent(fn ($request) => str_contains($request->url(), '/rest/posts'));
});
