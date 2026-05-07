<?php

declare(strict_types=1);

namespace App\Models\Traits;

use App\Features\MemberLimit;
use App\Features\MonthlyCreditsLimit;
use App\Features\SocialAccountLimit;
use App\Features\WorkspaceLimit;
use App\Models\AiUsageLog;
use App\Models\Invite;
use Laravel\Pennant\Feature;

/**
 * Provides account-level usage counts and plan-resolved feature limits.
 *
 * The `featureLimits()` call resolves Pennant features for the account,
 * which writes to the features cache table on first access. Pennant cache
 * is invalidated automatically when `plan_id` changes (see Account::booted).
 */
trait HasUsage
{
    /**
     * @return array{workspaceCount: int, socialAccountCount: int, memberCount: int, pendingInviteCount: int, postCount: int, creditsUsed: int}
     */
    public function usage(): array
    {
        $workspaces = $this->workspaces()
            ->withCount(['socialAccounts', 'posts'])
            ->get();

        return [
            'workspaceCount' => $workspaces->count(),
            'socialAccountCount' => (int) $workspaces->sum('social_accounts_count'),
            'memberCount' => $this->users()->count(),
            'pendingInviteCount' => Invite::where('account_id', $this->id)
                ->whereNull('accepted_at')
                ->count(),
            'postCount' => (int) $workspaces->sum('posts_count'),
            'creditsUsed' => AiUsageLog::monthlyCredits($this->id),
        ];
    }

    /**
     * @return array{workspaceLimit: int, socialAccountLimit: int, memberLimit: int, monthlyCreditsLimit: int}
     */
    public function featureLimits(): array
    {
        return [
            'workspaceLimit' => Feature::for($this)->value(WorkspaceLimit::class),
            'socialAccountLimit' => Feature::for($this)->value(SocialAccountLimit::class),
            'memberLimit' => Feature::for($this)->value(MemberLimit::class),
            'monthlyCreditsLimit' => Feature::for($this)->value(MonthlyCreditsLimit::class),
        ];
    }
}
