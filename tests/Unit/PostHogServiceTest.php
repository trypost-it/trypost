<?php

declare(strict_types=1);

use App\Jobs\PostHog\SendEvent;
use App\Models\Account;
use App\Models\Plan;
use App\Services\PostHogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

test('capture dispatches job when api key is configured', function () {
    Queue::fake();
    config(['services.posthog.api_key' => 'phc_test_key']);

    $service = new PostHogService;
    $service->capture('user-123', 'test_event', ['foo' => 'bar']);

    Queue::assertPushed(SendEvent::class, function ($job) {
        return $job->method === 'capture'
            && $job->payload['event'] === 'test_event'
            && $job->payload['distinctId'] === 'user-123';
    });
});

test('capture does not dispatch job when api key is missing', function () {
    Queue::fake();
    config(['services.posthog.api_key' => null]);

    $service = new PostHogService;
    $service->capture('user-123', 'test_event');

    Queue::assertNothingPushed();
});

test('identify dispatches job when api key is configured', function () {
    Queue::fake();
    config(['services.posthog.api_key' => 'phc_test_key']);

    $service = new PostHogService;
    $service->identify('user-123', ['$email' => 'test@example.com']);

    Queue::assertPushed(SendEvent::class, function ($job) {
        return $job->method === 'identify'
            && $job->payload['distinctId'] === 'user-123';
    });
});

test('identify does not dispatch job when api key is missing', function () {
    Queue::fake();
    config(['services.posthog.api_key' => null]);

    $service = new PostHogService;
    $service->identify('user-123', ['$email' => 'test@example.com']);

    Queue::assertNothingPushed();
});

test('group identify dispatches job when api key is configured', function () {
    Queue::fake();
    config(['services.posthog.api_key' => 'phc_test_key']);

    $service = new PostHogService;
    $service->groupIdentify('workspace', 'ws-123', ['name' => 'Test Workspace']);

    Queue::assertPushed(SendEvent::class, function ($job) {
        return $job->method === 'groupIdentify'
            && $job->payload['groupType'] === 'workspace'
            && $job->payload['groupKey'] === 'ws-123';
    });
});

test('group identify does not dispatch job when api key is missing', function () {
    Queue::fake();
    config(['services.posthog.api_key' => null]);

    $service = new PostHogService;
    $service->groupIdentify('workspace', 'ws-123', ['name' => 'Test']);

    Queue::assertNothingPushed();
});

test('capture auto-attaches account groups when account is supplied', function () {
    Queue::fake();
    config(['services.posthog.api_key' => 'phc_test_key']);

    $plan = Plan::query()->where('slug', 'starter')->first();
    $account = Account::factory()->create(['plan_id' => $plan?->id]);

    $service = new PostHogService;
    $service->capture('user-123', 'subscription.created', ['stripe_status' => 'active'], $account);

    Queue::assertPushed(SendEvent::class, function ($job) use ($account, $plan) {
        $payload = $job->payload;

        return $payload['event'] === 'subscription.created'
            && $payload['properties']['$groups']['account'] === (string) $account->id
            && $payload['properties']['account_id'] === (string) $account->id
            && $payload['properties']['plan'] === $plan?->name
            && $payload['properties']['stripe_status'] === 'active';
    });
});

test('capture without account does not attach group properties', function () {
    Queue::fake();
    config(['services.posthog.api_key' => 'phc_test_key']);

    $service = new PostHogService;
    $service->capture('user-123', 'page.viewed', ['url' => '/dashboard']);

    Queue::assertPushed(SendEvent::class, function ($job) {
        $properties = $job->payload['properties'];

        return ! array_key_exists('$groups', $properties)
            && ! array_key_exists('account_id', $properties)
            && ! array_key_exists('plan', $properties);
    });
});
