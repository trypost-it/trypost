<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\PostHog\SyncAccountUsage;
use App\Models\SocialAccount;
use App\Services\PostHogService;

class SocialAccountObserver
{
    public function created(SocialAccount $socialAccount): void
    {
        $this->syncUsage($socialAccount);
    }

    public function deleted(SocialAccount $socialAccount): void
    {
        $this->syncUsage($socialAccount);
    }

    private function syncUsage(SocialAccount $socialAccount): void
    {
        if (! PostHogService::isEnabled()) {
            return;
        }

        SyncAccountUsage::dispatch(
            (string) $socialAccount->workspace->account_id,
            (string) $socialAccount->workspace_id,
        );
    }
}
