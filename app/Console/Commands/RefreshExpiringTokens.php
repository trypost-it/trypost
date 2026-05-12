<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\SocialAccount\Status;
use App\Jobs\RefreshSocialToken;
use App\Models\SocialAccount;
use Illuminate\Console\Command;

class RefreshExpiringTokens extends Command
{
    protected $signature = 'social:refresh-expiring-tokens';

    protected $description = 'Proactively refresh tokens expiring in the next 2 hours (or already expired)';

    public function handle(): void
    {
        $count = 0;

        SocialAccount::query()
            ->where('status', Status::Connected)
            ->whereNotNull('token_expires_at')
            ->where('token_expires_at', '<=', now()->addHours(2))
            ->chunk(50, function ($accounts) use (&$count) {
                foreach ($accounts as $account) {
                    RefreshSocialToken::dispatch($account);
                    $count++;
                }
            });

        $this->info("Dispatched {$count} token refresh jobs.");
    }
}
