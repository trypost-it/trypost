<?php

declare(strict_types=1);

use App\Features\MonthlyCreditsLimit;
use App\Models\Account;
use App\Models\Plan;

test('returns plan monthly credits limit', function () {
    $plan = new Plan(['monthly_credits_limit' => 5000]);
    $account = new Account;
    $account->setRelation('plan', $plan);

    expect((new MonthlyCreditsLimit)->resolve($account))->toBe(5000);
});

test('falls back to 1000 when no plan', function () {
    $account = new Account;
    $account->setRelation('plan', null);

    expect((new MonthlyCreditsLimit)->resolve($account))->toBe(1000);
});
