<?php

declare(strict_types=1);

use App\Jobs\SendPostHogEvent;
use App\Services\PostHogService;
use Illuminate\Support\Facades\Queue;

test('capture dispatches job when api key is configured', function () {
    Queue::fake();
    config(['services.posthog.api_key' => 'phc_test_key']);

    $service = new PostHogService;
    $service->capture('user-123', 'test_event', ['foo' => 'bar']);

    Queue::assertPushed(SendPostHogEvent::class, function ($job) {
        return $job->calls[0]['method'] === 'capture'
            && $job->calls[0]['payload']['event'] === 'test_event'
            && $job->calls[0]['payload']['distinctId'] === 'user-123';
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

    Queue::assertPushed(SendPostHogEvent::class, function ($job) {
        return $job->calls[0]['method'] === 'identify'
            && $job->calls[0]['payload']['distinctId'] === 'user-123';
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

    Queue::assertPushed(SendPostHogEvent::class, function ($job) {
        return $job->calls[0]['method'] === 'groupIdentify'
            && $job->calls[0]['payload']['groupType'] === 'workspace'
            && $job->calls[0]['payload']['groupKey'] === 'ws-123';
    });
});

test('group identify does not dispatch job when api key is missing', function () {
    Queue::fake();
    config(['services.posthog.api_key' => null]);

    $service = new PostHogService;
    $service->groupIdentify('workspace', 'ws-123', ['name' => 'Test']);

    Queue::assertNothingPushed();
});
