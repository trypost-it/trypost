<?php

declare(strict_types=1);

use App\Enums\Post\Status as PostStatus;
use App\Enums\PostPlatform\Status as PlatformStatus;
use App\Enums\SocialAccount\Status as AccountStatus;
use App\Enums\UserWorkspace\Role;
use App\Events\PostPlatformStatusUpdated;
use App\Exceptions\Social\ErrorCategory;
use App\Exceptions\Social\LinkedInPublishException;
use App\Exceptions\TokenExpiredException;
use App\Jobs\PublishToSocialPlatform;
use App\Jobs\SendNotification;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Social\ConnectionVerifier;
use App\Services\Social\LinkedInPublisher;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Mail::fake();
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->socialAccount = SocialAccount::factory()->linkedin()->create([
        'workspace_id' => $this->workspace->id,
    ]);
    $this->post = Post::factory()->scheduled()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);
    $this->postPlatform = PostPlatform::factory()->linkedin()->create([
        'post_id' => $this->post->id,
        'social_account_id' => $this->socialAccount->id,
        'enabled' => true,
    ]);
});

test('publish to social platform marks platform as publishing', function () {
    Event::fake();

    $publisher = Mockery::mock(LinkedInPublisher::class);
    $publisher->shouldReceive('publish')->andReturn([
        'id' => 'post-123',
        'url' => 'https://linkedin.com/post/123',
    ]);

    $this->app->instance(LinkedInPublisher::class, $publisher);

    (new PublishToSocialPlatform($this->postPlatform))->handle();

    Event::assertDispatched(PostPlatformStatusUpdated::class);
});

test('publish to social platform marks platform as published on success', function () {
    Event::fake();

    $publisher = Mockery::mock(LinkedInPublisher::class);
    $publisher->shouldReceive('publish')->andReturn([
        'id' => 'post-123',
        'url' => 'https://linkedin.com/post/123',
    ]);

    $this->app->instance(LinkedInPublisher::class, $publisher);

    (new PublishToSocialPlatform($this->postPlatform))->handle();

    $this->postPlatform->refresh();
    expect($this->postPlatform->status)->toBe(PlatformStatus::Published);
    expect($this->postPlatform->platform_post_id)->toBe('post-123');
    expect($this->postPlatform->platform_url)->toBe('https://linkedin.com/post/123');
});

test('publish to social platform marks platform as failed on error', function () {
    Event::fake();

    $publisher = Mockery::mock(LinkedInPublisher::class);
    $publisher->shouldReceive('publish')->andThrow(new Exception('API Error'));

    $this->app->instance(LinkedInPublisher::class, $publisher);

    (new PublishToSocialPlatform($this->postPlatform))->handle();

    $this->postPlatform->refresh();
    expect($this->postPlatform->status)->toBe(PlatformStatus::Failed);
    expect($this->postPlatform->error_message)->toBe('API Error');
});

test('publish to social platform marks account as token expired on auth failure', function () {
    Event::fake();
    Mail::fake();

    $publisher = Mockery::mock(LinkedInPublisher::class);
    $publisher->shouldReceive('publish')->andThrow(new TokenExpiredException('Token expired', '401'));

    $this->app->instance(LinkedInPublisher::class, $publisher);

    (new PublishToSocialPlatform($this->postPlatform))->handle();

    $this->postPlatform->refresh();
    $this->socialAccount->refresh();

    expect($this->postPlatform->status)->toBe(PlatformStatus::Failed);
    expect($this->socialAccount->status)->toBe(AccountStatus::TokenExpired);
});

test('publish to social platform updates post status when all platforms finished', function () {
    Event::fake();

    $publisher = Mockery::mock(LinkedInPublisher::class);
    $publisher->shouldReceive('publish')->andReturn([
        'id' => 'post-123',
        'url' => 'https://linkedin.com/post/123',
    ]);

    $this->app->instance(LinkedInPublisher::class, $publisher);

    (new PublishToSocialPlatform($this->postPlatform))->handle();

    $this->post->refresh();
    expect($this->post->status)->toBe(PostStatus::Published);
});

test('publish to social platform marks post as partially published when some fail', function () {
    Event::fake();

    $socialAccount2 = SocialAccount::factory()->x()->create([
        'workspace_id' => $this->workspace->id,
    ]);

    $postPlatform2 = PostPlatform::factory()->x()->failed()->create([
        'post_id' => $this->post->id,
        'social_account_id' => $socialAccount2->id,
        'enabled' => true,
    ]);

    $publisher = Mockery::mock(LinkedInPublisher::class);
    $publisher->shouldReceive('publish')->andReturn([
        'id' => 'post-123',
        'url' => 'https://linkedin.com/post/123',
    ]);

    $this->app->instance(LinkedInPublisher::class, $publisher);

    (new PublishToSocialPlatform($this->postPlatform))->handle();

    $this->post->refresh();
    expect($this->post->status)->toBe(PostStatus::PartiallyPublished);
});

test('publish to social platform marks post as failed when all platforms fail', function () {
    Event::fake();

    $publisher = Mockery::mock(LinkedInPublisher::class);
    $publisher->shouldReceive('publish')->andThrow(new Exception('API Error'));

    $this->app->instance(LinkedInPublisher::class, $publisher);

    (new PublishToSocialPlatform($this->postPlatform))->handle();

    $this->post->refresh();
    expect($this->post->status)->toBe(PostStatus::Failed);
});

test('publish to social platform skips publishing when account is disconnected', function () {
    Event::fake();

    $this->socialAccount->update([
        'status' => AccountStatus::Disconnected,
        'disconnected_at' => now(),
    ]);

    $publisher = Mockery::mock(LinkedInPublisher::class);
    $publisher->shouldNotReceive('publish');

    $this->app->instance(LinkedInPublisher::class, $publisher);

    (new PublishToSocialPlatform($this->postPlatform))->handle();

    $this->postPlatform->refresh();
    expect($this->postPlatform->status)->toBe(PlatformStatus::Failed);
    expect($this->postPlatform->error_message)->toBe(__('posts.errors.account_disconnected'));
});

test('publish to social platform skips publishing when account token is expired', function () {
    Event::fake();

    $this->socialAccount->update([
        'status' => AccountStatus::TokenExpired,
        'disconnected_at' => now(),
    ]);

    $publisher = Mockery::mock(LinkedInPublisher::class);
    $publisher->shouldNotReceive('publish');

    $this->app->instance(LinkedInPublisher::class, $publisher);

    (new PublishToSocialPlatform($this->postPlatform))->handle();

    $this->postPlatform->refresh();
    expect($this->postPlatform->status)->toBe(PlatformStatus::Failed);
    expect($this->postPlatform->error_message)->toBe(__('posts.errors.account_token_expired'));
    expect($this->postPlatform->error_context['category'])->toBe('token_expired');
});

test('publish to social platform skips publishing when account is inactive', function () {
    Event::fake();

    $this->socialAccount->update(['is_active' => false]);

    $publisher = Mockery::mock(LinkedInPublisher::class);
    $publisher->shouldNotReceive('publish');

    $this->app->instance(LinkedInPublisher::class, $publisher);

    (new PublishToSocialPlatform($this->postPlatform))->handle();

    $this->postPlatform->refresh();
    expect($this->postPlatform->status)->toBe(PlatformStatus::Failed);
    expect($this->postPlatform->error_message)->toBe(__('posts.errors.account_inactive'));
});

test('publish to social platform dispatches success notification when all platforms published', function () {
    Event::fake();
    Queue::fake();

    $publisher = Mockery::mock(LinkedInPublisher::class);
    $publisher->shouldReceive('publish')->andReturn([
        'id' => 'post-123',
        'url' => 'https://linkedin.com/post/123',
    ]);

    $this->app->instance(LinkedInPublisher::class, $publisher);

    $this->workspace->members()->attach($this->user->id, ['role' => Role::Member->value]);

    (new PublishToSocialPlatform($this->postPlatform))->handle();

    $this->post->refresh();
    expect($this->post->status)->toBe(PostStatus::Published);
    Queue::assertPushed(SendNotification::class);
});

test('publish to social platform dispatches failure notification when platform fails', function () {
    Event::fake();
    Queue::fake();

    $publisher = Mockery::mock(LinkedInPublisher::class);
    $publisher->shouldReceive('publish')->andThrow(new Exception('API error'));

    $this->app->instance(LinkedInPublisher::class, $publisher);

    $this->workspace->members()->attach($this->user->id, ['role' => Role::Member->value]);

    (new PublishToSocialPlatform($this->postPlatform))->handle();

    $this->post->refresh();
    expect($this->post->status)->toBe(PostStatus::Failed);
    Queue::assertPushed(SendNotification::class);
});

test('it retries with token refresh when token expires during publish', function () {
    Event::fake();

    $callCount = 0;
    $publisher = Mockery::mock(LinkedInPublisher::class);
    $publisher->shouldReceive('publish')
        ->twice()
        ->andReturnUsing(function () use (&$callCount) {
            $callCount++;
            if ($callCount === 1) {
                throw new TokenExpiredException('Token expired', '401');
            }

            return ['id' => 'post-123', 'url' => 'https://linkedin.com/post/123'];
        });

    $this->app->instance(LinkedInPublisher::class, $publisher);

    $verifier = Mockery::mock(ConnectionVerifier::class);
    $verifier->shouldReceive('verify')->once()->andReturn(true);

    $this->app->instance(ConnectionVerifier::class, $verifier);

    (new PublishToSocialPlatform($this->postPlatform))->handle();

    $this->postPlatform->refresh();
    $this->socialAccount->refresh();

    expect($this->postPlatform->status)->toBe(PlatformStatus::Published);
    expect($this->socialAccount->status)->not->toBe(AccountStatus::Disconnected);
});

test('it marks account as token expired when refresh fails during publish retry', function () {
    Event::fake();

    $publisher = Mockery::mock(LinkedInPublisher::class);
    $publisher->shouldReceive('publish')
        ->once()
        ->andThrow(new TokenExpiredException('Token expired', '401'));

    $this->app->instance(LinkedInPublisher::class, $publisher);

    $verifier = Mockery::mock(ConnectionVerifier::class);
    $verifier->shouldReceive('verify')->once()->andThrow(new Exception('Refresh failed'));

    $this->app->instance(ConnectionVerifier::class, $verifier);

    (new PublishToSocialPlatform($this->postPlatform))->handle();

    $this->postPlatform->refresh();
    $this->socialAccount->refresh();

    expect($this->postPlatform->status)->toBe(PlatformStatus::Failed);
    expect($this->socialAccount->status)->toBe(AccountStatus::TokenExpired);
});

test('publish to social platform skips if already published (idempotency)', function () {
    Event::fake();

    // Mark as already published
    $this->postPlatform->update([
        'status' => PlatformStatus::Published,
        'platform_post_id' => 'existing-123',
    ]);

    $publisher = Mockery::mock(LinkedInPublisher::class);
    $publisher->shouldNotReceive('publish');

    $this->app->instance(LinkedInPublisher::class, $publisher);

    (new PublishToSocialPlatform($this->postPlatform))->handle();

    // Should not have called publish
    $this->postPlatform->refresh();
    expect($this->postPlatform->platform_post_id)->toBe('existing-123');
});

test('publish to social platform saves error context on generic failure', function () {
    Event::fake();

    $this->post->update(['content' => 'Test content here']);

    $publisher = Mockery::mock(LinkedInPublisher::class);
    $publisher->shouldReceive('publish')->andThrow(new Exception('Something broke'));

    $this->app->instance(LinkedInPublisher::class, $publisher);

    (new PublishToSocialPlatform($this->postPlatform))->handle();

    $this->postPlatform->refresh();
    expect($this->postPlatform->error_context)->toBeArray();
    expect($this->postPlatform->error_context['category'])->toBe('unknown');
    expect($this->postPlatform->error_context['failed_at'])->toBeString();
    expect($this->postPlatform->error_context['content_length'])->toBe(17);
    expect($this->postPlatform->error_context['media_count'])->toBe(0);
});

test('publish to social platform saves error context on social publish exception', function () {
    Event::fake();

    $this->post->update(['content' => 'Hello world']);

    $publisher = Mockery::mock(LinkedInPublisher::class);
    $publisher->shouldReceive('publish')->andThrow(
        new LinkedInPublishException(
            'Not authorized to post',
            ErrorCategory::Permission,
            '403',
            '{"error": "forbidden"}',
        )
    );

    $this->app->instance(LinkedInPublisher::class, $publisher);

    (new PublishToSocialPlatform($this->postPlatform))->handle();

    $this->postPlatform->refresh();
    expect($this->postPlatform->error_context)->toBeArray();
    expect($this->postPlatform->error_context['category'])->toBe('permission');
    expect($this->postPlatform->error_context['platform_error_code'])->toBe('403');
    expect($this->postPlatform->error_context['content_length'])->toBe(11);
});

test('publish to social platform fails when scopes are missing', function () {
    Event::fake();

    $this->socialAccount->update(['scopes' => ['user.info.basic']]); // missing w_member_social
    $this->postPlatform->refresh();

    (new PublishToSocialPlatform($this->postPlatform))->handle();

    $this->postPlatform->refresh();
    expect($this->postPlatform->status)->toBe(PlatformStatus::Failed);
    expect($this->postPlatform->error_message)->toContain('Missing permissions');
    expect($this->postPlatform->error_context['category'])->toBe('permission');
    expect($this->postPlatform->error_context['missing_scopes'])->toContain('w_member_social');
});

test('publish to social platform saves error context on token expired', function () {
    Event::fake();
    Mail::fake();

    $publisher = Mockery::mock(LinkedInPublisher::class);
    $publisher->shouldReceive('publish')->andThrow(new TokenExpiredException('Token expired', '190'));

    $this->app->instance(LinkedInPublisher::class, $publisher);

    $verifier = Mockery::mock(ConnectionVerifier::class);
    $verifier->shouldReceive('verify')->andThrow(new TokenExpiredException('Refresh failed'));
    $this->app->instance(ConnectionVerifier::class, $verifier);

    (new PublishToSocialPlatform($this->postPlatform))->handle();

    $this->postPlatform->refresh();
    expect($this->postPlatform->error_context)->toBeArray();
    expect($this->postPlatform->error_context['category'])->toBe('token_expired');
    expect($this->postPlatform->error_context['platform_error_code'])->toBe('190');
});
