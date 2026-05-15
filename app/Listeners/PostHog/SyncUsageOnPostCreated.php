<?php

declare(strict_types=1);

namespace App\Listeners\PostHog;

use App\Events\PostCreated;
use App\Jobs\PostHog\SyncAccountUsage;
use App\Services\PostHogService;

class SyncUsageOnPostCreated
{
    public function handle(PostCreated $event): void
    {
        if (! PostHogService::isEnabled()) {
            return;
        }

        $workspace = $event->post->workspace;

        SyncAccountUsage::dispatch((string) $workspace->account_id, (string) $workspace->id);
    }
}
