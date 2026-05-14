<?php

declare(strict_types=1);

namespace App\Actions\Plan;

use App\Enums\Plan\Slug;
use App\Enums\Post\Status as PostStatus;
use App\Features\MemberLimit;
use App\Features\ScheduledPostsLimit;
use App\Features\SocialAccountLimit;
use App\Features\WorkspaceLimit;
use App\Models\Account;
use App\Models\Post;
use Laravel\Pennant\Feature;

class DetectPlanViolations
{
    /**
     * @return list<array{dimension: string, current: int, limit: int, label_key: string, manage_route: string}>
     */
    public static function execute(Account $account): array
    {
        // Fast path: only Free plan can realistically violate. Paid plans have
        // limits well above any usage that an active customer would have.
        if ($account->plan?->slug !== Slug::Free) {
            return [];
        }

        $violations = [];
        $pennant = Feature::for($account);

        // Social accounts (sum across all workspaces)
        $socialLimit = (int) $pennant->value(SocialAccountLimit::class);
        $socialCurrent = (int) $account->workspaces()->withCount('socialAccounts')->get()->sum('social_accounts_count');
        if ($socialCurrent > $socialLimit) {
            $violations[] = [
                'dimension' => 'social_accounts',
                'current' => $socialCurrent,
                'limit' => $socialLimit,
                'label_key' => 'compliance.dimensions.social_accounts',
                'manage_route' => 'app.accounts',
            ];
        }

        // Workspaces
        $workspaceLimit = (int) $pennant->value(WorkspaceLimit::class);
        $workspaceCurrent = (int) $account->workspaces()->count();
        if ($workspaceCurrent > $workspaceLimit) {
            $violations[] = [
                'dimension' => 'workspaces',
                'current' => $workspaceCurrent,
                'limit' => $workspaceLimit,
                'label_key' => 'compliance.dimensions.workspaces',
                'manage_route' => 'app.workspaces.index',
            ];
        }

        // Members
        $memberLimit = (int) $pennant->value(MemberLimit::class);
        $memberCurrent = (int) $account->users()->count();
        if ($memberCurrent > $memberLimit) {
            $violations[] = [
                'dimension' => 'members',
                'current' => $memberCurrent,
                'limit' => $memberLimit,
                'label_key' => 'compliance.dimensions.members',
                'manage_route' => 'app.members',
            ];
        }

        // Scheduled posts queue (only blocks if Free; null/unlimited for paid)
        $scheduledLimit = $pennant->value(ScheduledPostsLimit::class);
        if ($scheduledLimit !== null) {
            $scheduledCurrent = (int) Post::whereHas('workspace', fn ($q) => $q->where('account_id', $account->id))
                ->where('status', PostStatus::Scheduled)
                ->where('scheduled_at', '>', now())
                ->count();
            if ($scheduledCurrent > (int) $scheduledLimit) {
                $violations[] = [
                    'dimension' => 'scheduled_posts',
                    'current' => $scheduledCurrent,
                    'limit' => (int) $scheduledLimit,
                    'label_key' => 'compliance.dimensions.scheduled_posts',
                    'manage_route' => 'app.posts.index',
                ];
            }
        }

        return $violations;
    }
}
