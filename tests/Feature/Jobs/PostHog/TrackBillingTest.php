<?php

declare(strict_types=1);

use App\Jobs\PostHog\SendEvent;
use App\Jobs\PostHog\SyncUser;
use App\Jobs\PostHog\TrackBilling;
use App\Models\Account;
use App\Models\Plan;
use App\Models\User;
use App\Services\PostHogService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config(['services.posthog.api_key' => 'phc_test_key']);

    $this->account = Account::factory()->create([
        'plan_id' => Plan::query()->where('slug', 'starter')->first()?->id,
    ]);
    $this->user = User::factory()->create(['account_id' => $this->account->id]);
    $this->account->update(['owner_id' => $this->user->id]);

    $this->payload = [
        'type' => 'customer.subscription.updated',
        'data' => ['object' => ['customer' => 'cus_test', 'status' => 'active']],
    ];
});

test('job is queued on the posthog queue', function () {
    $job = new TrackBilling((string) $this->account->id, 'subscription.updated', $this->payload);

    expect($job->queue)->toBe('posthog');
});

test('handle captures event on the owner profile with account group attached', function () {
    Queue::fake();

    (new TrackBilling((string) $this->account->id, 'subscription.updated', $this->payload))
        ->handle(app(PostHogService::class));

    Queue::assertPushed(SendEvent::class, function ($job) {
        $call = $job->calls[0];

        return $call['method'] === 'capture'
            && $call['payload']['event'] === 'subscription.updated'
            && $call['payload']['distinctId'] === (string) $this->user->id
            && $call['payload']['properties']['$groups']['account'] === (string) $this->account->id
            && $call['payload']['properties']['stripe_status'] === 'active';
    });
});

test('handle dispatches SyncUser for the account owner', function () {
    Bus::fake([SyncUser::class]);

    (new TrackBilling((string) $this->account->id, 'subscription.cancelled', $this->payload))
        ->handle(app(PostHogService::class));

    Bus::assertDispatched(
        SyncUser::class,
        fn ($job) => $job->userId === (string) $this->user->id,
    );
});

test('handle returns silently when account does not exist', function () {
    Queue::fake();
    Bus::fake([SyncUser::class]);

    (new TrackBilling('00000000-0000-0000-0000-000000000000', 'subscription.created', $this->payload))
        ->handle(app(PostHogService::class));

    Queue::assertNothingPushed();
    Bus::assertNotDispatched(SyncUser::class);
});

test('handle returns silently when account has no owner', function () {
    $this->account->update(['owner_id' => null]);
    Queue::fake();
    Bus::fake([SyncUser::class]);

    (new TrackBilling((string) $this->account->id, 'subscription.created', $this->payload))
        ->handle(app(PostHogService::class));

    Queue::assertNothingPushed();
    Bus::assertNotDispatched(SyncUser::class);
});

test('handle does not push a PostHog network call when api key is unset', function () {
    config(['services.posthog.api_key' => null]);
    Queue::fake();

    (new TrackBilling((string) $this->account->id, 'subscription.created', $this->payload))
        ->handle(app(PostHogService::class));

    // SyncUser can still be queued but it would itself no-op when handled.
    // The contract of this job is: no PostHog network call without a key.
    Queue::assertNotPushed(SendEvent::class);
});
