<?php

declare(strict_types=1);

namespace App\Models\Traits;

use App\Features\BlockedNetworks;
use App\Features\CanUseAi;
use App\Features\CanUseAnalytics;
use App\Features\MemberLimit;
use App\Features\MonthlyCreditsLimit;
use App\Features\ScheduledPostsLimit;
use App\Features\SocialAccountLimit;
use App\Features\WorkspaceLimit;
use App\Models\AiUsageLog;
use App\Models\Invite;
use App\Models\Post;
use Illuminate\Support\Facades\Cache;
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
     * Cache TTL for the per-account post count. Posts are unbounded by plan
     * limits and not used for any quota gating, so a few minutes of staleness
     * is acceptable in exchange for skipping a potentially heavy aggregate
     * query on every authenticated request.
     */
    private const POST_COUNT_CACHE_TTL = 300;

    /**
     * @return array{workspaceCount: int, socialAccountCount: int, memberCount: int, pendingInviteCount: int, postCount: int, creditsUsed: int}
     */
    public function usage(): array
    {
        $workspaces = $this->workspaces()
            ->withCount('socialAccounts')
            ->get();

        return [
            'workspaceCount' => $workspaces->count(),
            'socialAccountCount' => (int) $workspaces->sum('social_accounts_count'),
            'memberCount' => $this->users()->count(),
            'pendingInviteCount' => Invite::where('account_id', $this->id)
                ->whereNull('accepted_at')
                ->count(),
            'postCount' => $this->cachedPostCount($workspaces->pluck('id')->all()),
            'creditsUsed' => AiUsageLog::monthlyCredits($this->id),
        ];
    }

    /**
     * @return array{workspaceLimit: int, socialAccountLimit: int, memberLimit: int, monthlyCreditsLimit: int, canUseAi: bool, canUseAnalytics: bool, blockedNetworks: array<int, string>, scheduledPostsLimit: int|null}
     */
    public function featureLimits(): array
    {
        $pennant = Feature::for($this);

        return [
            'workspaceLimit' => $pennant->value(WorkspaceLimit::class),
            'socialAccountLimit' => $pennant->value(SocialAccountLimit::class),
            'memberLimit' => $pennant->value(MemberLimit::class),
            'monthlyCreditsLimit' => $pennant->value(MonthlyCreditsLimit::class),
            'canUseAi' => $pennant->value(CanUseAi::class),
            'canUseAnalytics' => $pennant->value(CanUseAnalytics::class),
            'blockedNetworks' => $pennant->value(BlockedNetworks::class),
            'scheduledPostsLimit' => $pennant->value(ScheduledPostsLimit::class),
        ];
    }

    /**
     * The `(int)` cast is load-bearing: Laravel's RedisStore stores numeric
     * values raw (for atomic INCR) and returns them as strings on read.
     *
     * @param  array<int, string>  $workspaceIds
     */
    private function cachedPostCount(array $workspaceIds): int
    {
        if (empty($workspaceIds)) {
            return 0;
        }

        return (int) Cache::remember(
            "account:{$this->id}:posts_count",
            self::POST_COUNT_CACHE_TTL,
            fn () => Post::whereIn('workspace_id', $workspaceIds)->count(),
        );
    }
}
