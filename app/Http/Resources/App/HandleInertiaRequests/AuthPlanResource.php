<?php

declare(strict_types=1);

namespace App\Http\Resources\App\HandleInertiaRequests;

use App\Models\Account;
use App\Models\Plan;

class AuthPlanResource
{
    /**
     * @return array<string, mixed>
     */
    public static function make(Account $account, Plan $plan): array
    {
        $subscription = $account->subscription(Account::SUBSCRIPTION_NAME);
        $interval = ($subscription && $subscription->stripe_price === $plan->stripe_yearly_price_id)
            ? 'yearly'
            : 'monthly';

        return [
            'id' => $plan->id,
            'slug' => $plan->slug->value,
            'name' => $plan->name,
            'interval' => $interval,
            'allowed_networks' => $plan->allowed_networks,
            'can_use_ai' => $plan->can_use_ai,
            'can_use_analytics' => $plan->can_use_analytics,
            'scheduled_posts_limit' => $plan->scheduled_posts_limit,
            'social_account_limit' => $plan->social_account_limit,
        ];
    }
}
