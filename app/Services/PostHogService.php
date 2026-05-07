<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\PostHog\SendEvent;
use App\Models\Account;
use Illuminate\Support\Facades\Log;

class PostHogService
{
    /**
     * Whether PostHog tracking should run at all. Controlled by the explicit
     * `POSTHOG_ENABLED` env var so self-hosted installs that inherited an
     * example `POSTHOG_API_KEY` from a config snippet do not silently send
     * events upstream. The SaaS deployment opts in via `POSTHOG_ENABLED=true`.
     */
    public static function isEnabled(): bool
    {
        return (bool) config('services.posthog.enabled')
            && (bool) config('services.posthog.api_key');
    }

    /**
     * Capture an event for the given distinct id. When `$account` is supplied,
     * the workspace/plan group is auto-attached so the event is filterable in
     * PostHog by `$groups.account` and the `account_id` / `plan` properties.
     *
     * @param  array<string, mixed>  $properties
     */
    public function capture(string $distinctId, string $event, array $properties = [], ?Account $account = null): void
    {
        if (! self::isEnabled()) {
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
        if (! self::isEnabled()) {
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
        if (! self::isEnabled()) {
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
            SendEvent::dispatch($method, $payload);
        } catch (\Throwable $e) {
            Log::warning('PostHogService: failed to dispatch event', ['method' => $method, 'error' => $e->getMessage()]);
        }
    }
}
