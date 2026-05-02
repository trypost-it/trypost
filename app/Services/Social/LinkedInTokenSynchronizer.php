<?php

declare(strict_types=1);

namespace App\Services\Social;

use App\Enums\SocialAccount\Platform;
use App\Models\SocialAccount;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Collection;

class LinkedInTokenSynchronizer
{
    /**
     * Sync tokens from LinkedIn personal to LinkedIn Page (or vice versa).
     * When one account gets a new token, update every linked account in the
     * same workspace so multiple pages sharing the same admin keep working.
     */
    public function syncTokens(SocialAccount $sourceAccount): void
    {
        $workspace = $sourceAccount->workspace;
        $linkedUserId = $this->getLinkedInUserId($sourceAccount);

        if (! $linkedUserId) {
            return;
        }

        $targets = $this->findLinkedAccounts($workspace, $sourceAccount, $linkedUserId);

        foreach ($targets as $target) {
            $target->update([
                'access_token' => $sourceAccount->access_token,
                'refresh_token' => $sourceAccount->refresh_token,
                'token_expires_at' => $sourceAccount->token_expires_at,
            ]);

            if ($target->isDisconnected()) {
                $target->markAsConnected();
            }
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
     * Find every linked LinkedIn account (personal <-> page) in the same workspace.
     *
     * @return Collection<int, SocialAccount>
     */
    private function findLinkedAccounts(Workspace $workspace, SocialAccount $sourceAccount, string $linkedUserId): Collection
    {
        if ($sourceAccount->platform === Platform::LinkedIn) {
            // Source is personal: every page admin'd by this LinkedIn user.
            return $workspace->socialAccounts()
                ->where('platform', Platform::LinkedInPage->value)
                ->whereJsonContains('meta->admin_user_id', $linkedUserId)
                ->get();
        }

        if ($sourceAccount->platform === Platform::LinkedInPage) {
            // Source is page: the personal LinkedIn account belonging to its admin.
            return $workspace->socialAccounts()
                ->where('platform', Platform::LinkedIn->value)
                ->where('platform_user_id', $linkedUserId)
                ->get();
        }

        return new Collection;
    }
}
