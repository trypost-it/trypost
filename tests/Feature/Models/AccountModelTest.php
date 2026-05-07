<?php

declare(strict_types=1);

use App\Features\MemberLimit;
use App\Features\MonthlyCreditsLimit;
use App\Features\SocialAccountLimit;
use App\Features\WorkspaceLimit;
use App\Models\Account;
use App\Models\Plan;
use Illuminate\Support\Facades\DB;
use Laravel\Pennant\Feature;

test('forgetPlanFeatureCache drops the cached plan-scoped features', function () {
    $starter = Plan::where('slug', 'starter')->first();
    $plus = Plan::where('slug', 'plus')->first();

    $account = Account::factory()->create(['plan_id' => $starter->id]);

    // Prime the Pennant cache against the starter plan.
    Feature::for($account)->value(WorkspaceLimit::class);
    Feature::for($account)->value(SocialAccountLimit::class);
    Feature::for($account)->value(MemberLimit::class);
    Feature::for($account)->value(MonthlyCreditsLimit::class);

    expect(DB::table('features')->where('scope', 'account|'.$account->id)->count())
        ->toBe(4);

    // Move the account to a plan with different limits and forget the cache.
    $account->update(['plan_id' => $plus->id]);
    $account->forgetPlanFeatureCache();
    $account->load('plan');

    expect(DB::table('features')->where('scope', 'account|'.$account->id)->count())
        ->toBe(0);

    expect(Feature::for($account)->value(WorkspaceLimit::class))->toBe($plus->workspace_limit);
    expect(Feature::for($account)->value(SocialAccountLimit::class))->toBe($plus->social_account_limit);
    expect(Feature::for($account)->value(MemberLimit::class))->toBe($plus->member_limit);
    expect(Feature::for($account)->value(MonthlyCreditsLimit::class))->toBe($plus->monthly_credits_limit);
});
