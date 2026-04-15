<?php

declare(strict_types=1);

use App\Features\WorkspaceLimit;
use App\Models\Account;
use App\Models\Plan;

test('returns plan workspace limit', function () {
    $plan = new Plan(['workspace_limit' => 5]);
    $account = new Account;
    $account->setRelation('plan', $plan);

    expect((new WorkspaceLimit)->resolve($account))->toBe(5);
});

test('falls back to 1 when no plan', function () {
    $account = new Account;
    $account->setRelation('plan', null);

    expect((new WorkspaceLimit)->resolve($account))->toBe(1);
});
