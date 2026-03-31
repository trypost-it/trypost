<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use PostHog\PostHog;

class SendPostHogEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 15;

    /**
     * @param  array<array{method: string, payload: array<string, mixed>}>  $calls
     */
    public function __construct(
        public array $calls,
    ) {
        $this->onQueue('posthog');
    }

    public function handle(): void
    {
        if (! config('services.posthog.api_key')) {
            return;
        }

        foreach ($this->calls as $call) {
            match (data_get($call, 'method')) {
                'capture' => PostHog::capture(data_get($call, 'payload')),
                'identify' => PostHog::identify(data_get($call, 'payload')),
                'groupIdentify' => PostHog::groupIdentify(data_get($call, 'payload')),
                default => Log::warning('SendPostHogEvent: unknown method', ['method' => data_get($call, 'method')]),
            };
        }

        PostHog::flush();
    }
}
