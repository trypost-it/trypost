<?php

declare(strict_types=1);

use App\Enums\SocialAccount\Platform;
use App\Models\Media;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\Workspace;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $result = createApiTestToken();
    $this->user = $result['user'];
    $this->workspace = $result['workspace'];
    $this->plainToken = $result['plain_token'];

    $this->socialAccount = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::LinkedIn,
    ]);

    $this->post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    PostPlatform::factory()->linkedin()->create([
        'post_id' => $this->post->id,
        'social_account_id' => $this->socialAccount->id,
        'enabled' => true,
    ]);

    Storage::fake();
});

it('attaches media from url', function () {
    Http::fake([
        'cdn.example.com/photo.png' => Http::response(
            file_get_contents(__DIR__.'/../../fixtures/1x1.png'),
            200,
            ['Content-Type' => 'image/png'],
        ),
    ]);

    $this->withHeaders(['Authorization' => 'Bearer '.$this->plainToken])
        ->postJson(route('api.posts.attach-media', $this->post), [
            'urls' => ['https://cdn.example.com/photo.png'],
        ])
        ->assertOk()
        ->assertJsonPath('attached_count', 1)
        ->assertJsonPath('failed_urls', []);

    expect(Media::where('mediable_id', $this->workspace->id)->count())->toBe(1);
    expect($this->post->fresh()->media)->toHaveCount(1);
});

it('reports failures for unreachable urls', function () {
    Http::fake([
        'cdn.example.com/missing.png' => Http::response(null, 404),
    ]);

    $this->withHeaders(['Authorization' => 'Bearer '.$this->plainToken])
        ->postJson(route('api.posts.attach-media', $this->post), [
            'urls' => ['https://cdn.example.com/missing.png'],
        ])
        ->assertOk()
        ->assertJsonPath('attached_count', 0)
        ->assertJsonPath('failed_urls.0', 'https://cdn.example.com/missing.png');
});

it('cannot attach media to a post from another workspace', function () {
    $other = Workspace::factory()->create();
    $post = Post::factory()->create(['workspace_id' => $other->id, 'user_id' => $this->user->id]);

    $this->withHeaders(['Authorization' => 'Bearer '.$this->plainToken])
        ->postJson(route('api.posts.attach-media', $post), [
            'urls' => ['https://cdn.example.com/photo.png'],
        ])
        ->assertNotFound();
});

it('previews per platform with sanitized content and length', function () {
    $this->post->update(['content' => str_repeat('a', 500)]);

    $this->withHeaders(['Authorization' => 'Bearer '.$this->plainToken])
        ->getJson(route('api.posts.preview', $this->post))
        ->assertOk()
        ->assertJsonStructure([
            'post_id',
            'original_content',
            'original_length',
            'platforms' => [
                '*' => [
                    'post_platform_id',
                    'platform',
                    'content_type',
                    'sanitized_content',
                    'sanitized_length',
                    'max_content_length',
                    'truncated',
                ],
            ],
        ])
        ->assertJsonPath('original_length', 500);
});

it('returns metrics shape including unsupported reason for unpublished platforms', function () {
    $this->withHeaders(['Authorization' => 'Bearer '.$this->plainToken])
        ->getJson(route('api.posts.metrics', $this->post))
        ->assertOk()
        ->assertJsonStructure([
            'post_id',
            'platforms' => [
                '*' => [
                    'post_platform_id',
                    'platform',
                    'status',
                    'platform_post_id',
                    'platform_url',
                    'metrics',
                ],
            ],
        ])
        ->assertJsonPath('platforms.0.metrics.unsupported', true)
        ->assertJsonPath('platforms.0.metrics.reason', 'not_published');
});

it('cannot get metrics from another workspace post', function () {
    $other = Workspace::factory()->create();
    $post = Post::factory()->create(['workspace_id' => $other->id, 'user_id' => $this->user->id]);

    $this->withHeaders(['Authorization' => 'Bearer '.$this->plainToken])
        ->getJson(route('api.posts.metrics', $post))
        ->assertNotFound();

    $this->withHeaders(['Authorization' => 'Bearer '.$this->plainToken])
        ->getJson(route('api.posts.preview', $post))
        ->assertNotFound();
});

it('rejects attach media payload without urls', function () {
    $this->withHeaders(['Authorization' => 'Bearer '.$this->plainToken])
        ->postJson(route('api.posts.attach-media', $this->post), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['urls']);
});
