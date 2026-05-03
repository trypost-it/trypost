<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Account;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class AccountPolicy
{
    public function update(User $user, Account $account): bool
    {
        return $user->id === $account->owner_id;
    }

    public function manageBilling(User $user, Account $account): bool
    {
        return $user->id === $account->owner_id;
    }

    /**
     * Authorize swapping the account's subscription to the given plan.
     *
     * Denies if the account's current usage exceeds any of the target plan's
     * limits (workspaces, social accounts, members + pending invites).
     */
    public function swapPlan(User $user, Account $account, Plan $plan): Response
    {
        if ($user->id !== $account->owner_id) {
            return Response::deny(__('billing.flash.cannot_manage'));
        }

        $usage = $account->usage();

        $checks = [
            'workspaces' => [
                'count' => $usage['workspaceCount'],
                'limit' => (int) $plan->workspace_limit,
            ],
            'social_accounts' => [
                'count' => $usage['socialAccountCount'],
                'limit' => (int) $plan->social_account_limit,
            ],
            'members' => [
                'count' => $usage['memberCount'] + $usage['pendingInviteCount'],
                'limit' => (int) $plan->member_limit,
            ],
        ];

        foreach ($checks as $type => $data) {
            if ($data['count'] > $data['limit']) {
                return Response::deny(__('billing.flash.cannot_downgrade.'.$type, [
                    'plan' => $plan->name,
                    'count' => (string) $data['count'],
                    'limit' => (string) $data['limit'],
                ]));
            }
        }

        return Response::allow();
    }
}
