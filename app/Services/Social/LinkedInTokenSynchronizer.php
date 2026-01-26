<?php

namespace App\Services\Social;

use App\Enums\SocialAccount\Platform;
use App\Models\SocialAccount;
use App\Models\Workspace;
use Illuminate\Support\Facades\Log;

class LinkedInTokenSynchronizer
{
    /**
     * Sync tokens from LinkedIn personal to LinkedIn Page (or vice versa).
     * When one account gets a new token, update the other if it exists.
     */
    public function syncTokens(SocialAccount $sourceAccount): void
    {
        $workspace = $sourceAccount->workspace;
        $linkedUserId = $this->getLinkedInUserId($sourceAccount);

        if (! $linkedUserId) {
            return;
        }

        $targetAccount = $this->findLinkedAccount($workspace, $sourceAccount, $linkedUserId);

        if (! $targetAccount) {
            return;
        }

        Log::info('Syncing LinkedIn tokens between accounts', [
            'source_id' => $sourceAccount->id,
            'source_platform' => $sourceAccount->platform->value,
            'target_id' => $targetAccount->id,
            'target_platform' => $targetAccount->platform->value,
        ]);

        $targetAccount->update([
            'access_token' => $sourceAccount->access_token,
            'refresh_token' => $sourceAccount->refresh_token,
            'token_expires_at' => $sourceAccount->token_expires_at,
        ]);

        if ($targetAccount->isDisconnected()) {
            $targetAccount->markAsConnected();
        }
    }

    /**
     * Get the LinkedIn user ID from an account.
     * For personal accounts, it's the platform_user_id.
     * For page accounts, it's stored in meta.admin_user_id.
     */
    private function getLinkedInUserId(SocialAccount $account): ?string
    {
        if ($account->platform === Platform::LinkedIn) {
            return $account->platform_user_id;
        }

        if ($account->platform === Platform::LinkedInPage) {
            return $account->meta['admin_user_id'] ?? null;
        }

        return null;
    }

    /**
     * Find the linked LinkedIn account (personal <-> page) in the same workspace.
     */
    private function findLinkedAccount(Workspace $workspace, SocialAccount $sourceAccount, string $linkedUserId): ?SocialAccount
    {
        if ($sourceAccount->platform === Platform::LinkedIn) {
            // Source is personal, find page account with same admin_user_id
            return $workspace->socialAccounts()
                ->where('platform', Platform::LinkedInPage->value)
                ->whereJsonContains('meta->admin_user_id', $linkedUserId)
                ->first();
        }

        if ($sourceAccount->platform === Platform::LinkedInPage) {
            // Source is page, find personal account with same platform_user_id
            return $workspace->socialAccounts()
                ->where('platform', Platform::LinkedIn->value)
                ->where('platform_user_id', $linkedUserId)
                ->first();
        }

        return null;
    }
}
