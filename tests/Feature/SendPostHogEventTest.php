<?php

declare(strict_types=1);

use App\Jobs\SendPostHogEvent;

test('job is queued on posthog queue', function () {
    $job = new SendPostHogEvent([
        ['method' => 'capture', 'payload' => ['distinctId' => 'user-1', 'event' => 'test']],
    ]);

    expect($job->queue)->toBe('posthog');
});

test('job skips execution when api key is missing', function () {
    config(['services.posthog.api_key' => null]);

    $job = new SendPostHogEvent([
        ['method' => 'capture', 'payload' => ['distinctId' => 'user-1', 'event' => 'test']],
    ]);

    // Should not throw - silently skips
    $job->handle();

    expect(true)->toBeTrue();
});

test('job has correct retry and timeout settings', function () {
    $job = new SendPostHogEvent([]);

    expect($job->tries)->toBe(3);
    expect($job->timeout)->toBe(15);
});
