<?php

declare(strict_types=1);

use App\Jobs\PostHog\SendEvent;
use App\Jobs\PostHog\SyncUser;
use App\Models\Account;
use App\Models\Plan;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Services\PostHogService;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function () {
    config(['services.posthog.api_key' => 'phc_test_key']);

    $this->account = Account::factory()->create([
        'plan_id' => Plan::query()->where('slug', 'starter')->first()?->id,
    ]);
    $this->user = User::factory()->create(['account_id' => $this->account->id]);
    $this->account->update(['owner_id' => $this->user->id]);
});

test('handle is a no-op when api key is unset', function () {
    config(['services.posthog.api_key' => null]);
    Queue::fake();

    (new SyncUser((string) $this->user->id))->handle(app(PostHogService::class));

    Queue::assertNothingPushed();
});

test('handle returns silently when user does not exist', function () {
    Queue::fake();

    (new SyncUser((string) Str::uuid()))->handle(app(PostHogService::class));

    Queue::assertNothingPushed();
});

test('handle identifies the user with email, name and signed_up_at', function () {
    Queue::fake();

    (new SyncUser((string) $this->user->id))->handle(app(PostHogService::class));

    Queue::assertPushed(SendEvent::class, function ($job) {
        return $job->method === 'identify'
            && $job->payload['distinctId'] === (string) $this->user->id
            && $job->payload['properties']['email'] === $this->user->email
            && $job->payload['properties']['name'] === $this->user->name
            && isset($job->payload['properties']['$set_once']['signed_up_at']);
    });
});

test('handle group-identifies the account with usage metrics', function () {
    $workspace = Workspace::factory()->create([
        'account_id' => $this->account->id,
        'user_id' => $this->user->id,
    ]);
    SocialAccount::factory()->count(2)->create(['workspace_id' => $workspace->id]);
    Post::factory()->count(3)->create([
        'workspace_id' => $workspace->id,
        'user_id' => $this->user->id,
    ]);

    Queue::fake();

    (new SyncUser((string) $this->user->id))->handle(app(PostHogService::class));

    Queue::assertPushed(SendEvent::class, function ($job) {
        if ($job->method !== 'groupIdentify' || $job->payload['groupType'] !== 'account') {
            return false;
        }

        $props = $job->payload['properties'];

        return $job->payload['groupKey'] === (string) $this->account->id
            && $props['workspaces_count'] === 1
            && $props['social_accounts_count'] === 2
            && $props['posts_count'] === 3
            && $props['members_count'] === 1
            && array_key_exists('plan', $props)
            && array_key_exists('has_active_subscription', $props)
            && array_key_exists('is_on_trial', $props);
    });
});

test('handle group-identifies the current workspace when set', function () {
    $workspace = Workspace::factory()->create([
        'account_id' => $this->account->id,
        'user_id' => $this->user->id,
    ]);
    SocialAccount::factory()->create(['workspace_id' => $workspace->id]);
    $this->user->update(['current_workspace_id' => $workspace->id]);

    Queue::fake();

    (new SyncUser((string) $this->user->id))->handle(app(PostHogService::class));

    Queue::assertPushed(SendEvent::class, function ($job) use ($workspace) {
        return $job->method === 'groupIdentify'
            && $job->payload['groupType'] === 'workspace'
            && $job->payload['groupKey'] === (string) $workspace->id
            && $job->payload['properties']['account_id'] === (string) $this->account->id
            && $job->payload['properties']['social_accounts_count'] === 1;
    });
});

test('handle skips workspace group identify when user has no current workspace', function () {
    $this->user->update(['current_workspace_id' => null]);

    Queue::fake();

    (new SyncUser((string) $this->user->id))->handle(app(PostHogService::class));

    Queue::assertNotPushed(SendEvent::class, function ($job) {
        return $job->method === 'groupIdentify' && $job->payload['groupType'] === 'workspace';
    });
});

test('job is queued on the posthog connection queue', function () {
    $job = new SyncUser((string) $this->user->id);

    expect($job->queue)->toBe('posthog');
});
