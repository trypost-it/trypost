<?php

use App\Enums\Post\Status as PostStatus;
use App\Enums\SocialAccount\Status as AccountStatus;
use App\Events\PostPlatformStatusUpdated;
use App\Exceptions\TokenExpiredException;
use App\Jobs\PublishToSocialPlatform;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Social\LinkedInPublisher;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
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
    expect($this->postPlatform->status)->toBe('published');
    expect($this->postPlatform->platform_post_id)->toBe('post-123');
    expect($this->postPlatform->platform_url)->toBe('https://linkedin.com/post/123');
});

test('publish to social platform marks platform as failed on error', function () {
    Event::fake();

    $publisher = Mockery::mock(LinkedInPublisher::class);
    $publisher->shouldReceive('publish')->andThrow(new \Exception('API Error'));

    $this->app->instance(LinkedInPublisher::class, $publisher);

    (new PublishToSocialPlatform($this->postPlatform))->handle();

    $this->postPlatform->refresh();
    expect($this->postPlatform->status)->toBe('failed');
    expect($this->postPlatform->error_message)->toBe('API Error');
});

test('publish to social platform disconnects account on token expired', function () {
    Event::fake();
    Mail::fake();

    $publisher = Mockery::mock(LinkedInPublisher::class);
    $publisher->shouldReceive('publish')->andThrow(new TokenExpiredException('Token expired', 401));

    $this->app->instance(LinkedInPublisher::class, $publisher);

    (new PublishToSocialPlatform($this->postPlatform))->handle();

    $this->postPlatform->refresh();
    $this->socialAccount->refresh();

    expect($this->postPlatform->status)->toBe('failed');
    expect($this->socialAccount->status)->toBe(AccountStatus::Disconnected);
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
    $publisher->shouldReceive('publish')->andThrow(new \Exception('API Error'));

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
    expect($this->postPlatform->status)->toBe('failed');
    expect($this->postPlatform->error_message)->toBe('Social account is disconnected');
});

test('publish to social platform skips publishing when account token is expired', function () {
    Event::fake();

    $this->socialAccount->update([
        'status' => AccountStatus::TokenExpired,
    ]);

    $publisher = Mockery::mock(LinkedInPublisher::class);
    $publisher->shouldNotReceive('publish');

    $this->app->instance(LinkedInPublisher::class, $publisher);

    (new PublishToSocialPlatform($this->postPlatform))->handle();

    $this->postPlatform->refresh();
    expect($this->postPlatform->status)->toBe('failed');
    expect($this->postPlatform->error_message)->toBe('Social account is disconnected');
});
