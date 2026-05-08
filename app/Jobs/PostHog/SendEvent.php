<?php

declare(strict_types=1);

namespace App\Jobs\PostHog;

use App\Services\PostHogService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use PostHog\PostHog;

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
        if (! PostHogService::isEnabled()) {
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
