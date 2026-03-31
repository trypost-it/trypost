<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\SendPostHogEvent;
use Illuminate\Support\Facades\Log;

class PostHogService
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function capture(string $distinctId, string $event, array $properties = []): void
    {
        $this->dispatch('capture', [
            'distinctId' => $distinctId,
            'event' => $event,
            'properties' => $properties,
        ]);
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    public function identify(string $distinctId, array $properties = []): void
    {
        $this->dispatch('identify', [
            'distinctId' => $distinctId,
            'properties' => $properties,
        ]);
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    public function groupIdentify(string $groupType, string $groupKey, array $properties = []): void
    {
        $this->dispatch('groupIdentify', [
            'groupType' => $groupType,
            'groupKey' => $groupKey,
            'properties' => $properties,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function dispatch(string $method, array $payload): void
    {
        if (! config('services.posthog.api_key')) {
            return;
        }

        try {
            SendPostHogEvent::dispatch([
                ['method' => $method, 'payload' => $payload],
            ]);
        } catch (\Throwable $e) {
            Log::warning('PostHogService: failed to dispatch event', ['method' => $method, 'error' => $e->getMessage()]);
        }
    }
}
