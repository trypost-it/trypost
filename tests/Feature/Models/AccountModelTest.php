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

test('booted hook flushes pennant cache when plan_id changes', function () {
    $starter = Plan::where('slug', 'starter')->first();
    $plus = Plan::where('slug', 'plus')->first();

    $account = Account::factory()->create(['plan_id' => $starter->id]);

    expect(Feature::for($account)->value(WorkspaceLimit::class))->toBe($starter->workspace_limit);
    expect(Feature::for($account)->value(SocialAccountLimit::class))->toBe($starter->social_account_limit);
    expect(Feature::for($account)->value(MemberLimit::class))->toBe($starter->member_limit);
    expect(Feature::for($account)->value(MonthlyCreditsLimit::class))->toBe($starter->monthly_credits_limit);

    $account->update(['plan_id' => $plus->id]);
    $account->load('plan');

    expect(Feature::for($account)->value(WorkspaceLimit::class))->toBe($plus->workspace_limit);
    expect(Feature::for($account)->value(SocialAccountLimit::class))->toBe($plus->social_account_limit);
    expect(Feature::for($account)->value(MemberLimit::class))->toBe($plus->member_limit);
    expect(Feature::for($account)->value(MonthlyCreditsLimit::class))->toBe($plus->monthly_credits_limit);
});

test('booted hook does not flush pennant when other fields change', function () {
    $plan = Plan::where('slug', 'plus')->first();
    $account = Account::factory()->create(['plan_id' => $plan->id]);

    Feature::for($account)->value(WorkspaceLimit::class);

    $cachedRow = DB::table('features')
        ->where('scope', 'account|'.$account->id)
        ->first();

    expect($cachedRow)->not->toBeNull();

    $account->update(['name' => 'Updated Name']);

    $stillCachedRow = DB::table('features')
        ->where('scope', 'account|'.$account->id)
        ->first();

    expect($stillCachedRow->id)->toBe($cachedRow->id);
});
