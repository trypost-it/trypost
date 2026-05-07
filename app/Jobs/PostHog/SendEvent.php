<?php

declare(strict_types=1);

namespace App\Jobs\PostHog;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use PostHog\PostHog;

/**
 * Low-level PostHog dispatch worker. Forwards a single API call (`capture`,
 * `identify`, `groupIdentify`) to the PostHog SDK. Higher-level jobs in this
 * namespace (`SyncUser`, `TrackBilling`) build the payloads and queue this
 * job to do the network work.
 *
 * No-op when `POSTHOG_API_KEY` is unset so self-hosted installs are
 * unaffected.
 */
class SendEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 15;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $method,
        public array $payload,
    ) {
        $this->onQueue('posthog');
    }

    public function handle(): void
    {
        if (! config('services.posthog.api_key')) {
            return;
        }

        match ($this->method) {
            'capture' => PostHog::capture($this->payload),
            'identify' => PostHog::identify($this->payload),
            'groupIdentify' => PostHog::groupIdentify($this->payload),
            default => Log::warning('PostHog SendEvent: unknown method', ['method' => $this->method]),
        };

        PostHog::flush();
    }
}
