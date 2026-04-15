<?php

declare(strict_types=1);

use App\Features\DataRetentionDays;
use App\Models\Account;
use App\Models\Plan;

test('returns plan data retention days', function () {
    $plan = new Plan(['data_retention_days' => 365]);
    $account = new Account;
    $account->setRelation('plan', $plan);

    expect((new DataRetentionDays)->resolve($account))->toBe(365);
});

test('falls back to 30 when no plan', function () {
    $account = new Account;
    $account->setRelation('plan', null);

    expect((new DataRetentionDays)->resolve($account))->toBe(30);
});
