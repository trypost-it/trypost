<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use App\Services\PostHogService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Centralised PostHog sync for a single user. Dispatched from places the
 * frontend can't observe (signup, Stripe webhooks) so the person profile
 * and account/workspace groups carry up-to-date properties without blocking
 * the calling request. Inertia navigations refresh group counts on the
 * client (see `syncPostHogContext` in resources/js/app.ts), so this job is
 * not needed on every domain trigger.
 *
 * Hierarchy mirrors the domain model:
 * - person  → User
 * - group `account`   → Account (billing/plan, parent of workspaces)
 * - group `workspace` → Workspace (collaboration unit, child of account)
 *
 * No-op when POSTHOG_API_KEY is unset (PostHogService short-circuits), so
 * self-hosted installs are unaffected.
 */
class SyncUserToPostHog implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(public string $userId)
    {
        $this->onQueue('posthog');
    }

    public function handle(PostHogService $postHog): void
    {
        $user = User::with(['account.plan', 'currentWorkspace'])->find($this->userId);

        if (! $user) {
            return;
        }

        $postHog->identify((string) $user->id, [
            'email' => $user->email,
            'name' => $user->name,
            '$set_once' => ['signed_up_at' => $user->created_at?->toIso8601String()],
        ]);

        if ($account = $user->account) {
            $usage = $account->usage();

            $postHog->groupIdentify('account', (string) $account->id, [
                'name' => $account->name,
                'plan' => $account->plan?->name,
                'plan_slug' => $account->plan?->slug,
                'has_active_subscription' => $account->hasActiveSubscription(),
                'is_on_trial' => $account->isOnTrial(),
                'workspaces_count' => $usage['workspaceCount'],
                'members_count' => $usage['memberCount'],
                'social_accounts_count' => $usage['socialAccountCount'],
                'posts_count' => $usage['postCount'],
                'pending_invites_count' => $usage['pendingInviteCount'],
                'credits_used' => $usage['creditsUsed'],
                'created_at' => $account->created_at?->toIso8601String(),
            ]);
        }

        if ($workspace = $user->currentWorkspace) {
            $postHog->groupIdentify('workspace', (string) $workspace->id, [
                'name' => $workspace->name,
                'account_id' => (string) $workspace->account_id,
                'social_accounts_count' => $workspace->socialAccounts()->count(),
                'posts_count' => $workspace->posts()->count(),
                'created_at' => $workspace->created_at?->toIso8601String(),
            ]);
        }
    }
}
