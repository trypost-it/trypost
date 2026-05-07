<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\SendPostHogEvent;
use App\Models\Account;
use Illuminate\Support\Facades\Log;

class PostHogService
{
    /**
     * Capture an event for the given distinct id. When `$account` is supplied,
     * the workspace/plan group is auto-attached so the event is filterable in
     * PostHog by `$groups.account` and the `account_id` / `plan` properties.
     *
     * @param  array<string, mixed>  $properties
     */
    public function capture(string $distinctId, string $event, array $properties = [], ?Account $account = null): void
    {
        if (! config('services.posthog.api_key')) {
            return;
        }

        $payload = [
            'distinctId' => $distinctId,
            'event' => $event,
            'properties' => $properties,
        ];

        if ($account) {
            $payload['properties']['$groups'] = ['account' => (string) $account->id];
            $payload['properties']['account_id'] = (string) $account->id;
            $payload['properties']['plan'] = $account->plan?->name;
        }

        $this->dispatch('capture', $payload);
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    public function identify(string $distinctId, array $properties = []): void
    {
        if (! config('services.posthog.api_key')) {
            return;
        }

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
        if (! config('services.posthog.api_key')) {
            return;
        }

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
        try {
            SendPostHogEvent::dispatch([
                ['method' => $method, 'payload' => $payload],
            ]);
        } catch (\Throwable $e) {
            Log::warning('PostHogService: failed to dispatch event', ['method' => $method, 'error' => $e->getMessage()]);
        }
    }
}
