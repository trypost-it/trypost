<?php

declare(strict_types=1);

namespace App\Listeners\PostHog;

use App\Events\PostDeleted;
use App\Jobs\PostHog\SyncAccountUsage;
use App\Models\Workspace;
use App\Services\PostHogService;

class SyncUsageOnPostDeleted
{
    public function handle(PostDeleted $event): void
    {
        if (! PostHogService::isEnabled()) {
            return;
        }

        $workspace = Workspace::findOrFail($event->workspaceId);

        SyncAccountUsage::dispatch((string) $workspace->account_id, (string) $workspace->id);
    }
}
