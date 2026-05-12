<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Exceptions\TokenExpiredException;
use App\Models\SocialAccount;
use App\Services\Social\ConnectionVerifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class RefreshSocialToken implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public SocialAccount $account) {}

    public function handle(ConnectionVerifier $verifier): void
    {
        try {
            $verifier->refreshToken($this->account);
        } catch (TokenExpiredException $e) {
            // refresh_token rejected by the provider (revoked / rotated /
            // expired beyond refresh). Mark the account so the user is
            // notified immediately instead of waiting for the next failed
            // publish or the daily verify pass.
            $this->account->markAsTokenExpired($e->getMessage());
        } catch (Throwable $e) {
            Log::warning('Proactive token refresh failed', [
                'account_id' => $this->account->id,
                'platform' => $this->account->platform->value,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
