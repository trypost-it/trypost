<?php

declare(strict_types=1);

use App\Features\MemberLimit;
use App\Models\Account;
use App\Models\Plan;

test('returns plan member limit', function () {
    $plan = new Plan(['member_limit' => 10]);
    $account = new Account;
    $account->setRelation('plan', $plan);

    expect((new MemberLimit)->resolve($account))->toBe(10);
});

test('falls back to 1 when no plan', function () {
    $account = new Account;
    $account->setRelation('plan', null);

    expect((new MemberLimit)->resolve($account))->toBe(1);
});
