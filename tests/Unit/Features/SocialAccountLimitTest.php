<?php

declare(strict_types=1);

use App\Features\SocialAccountLimit;
use App\Models\Account;
use App\Models\Plan;

test('returns plan social account limit', function () {
    $plan = new Plan(['social_account_limit' => 30]);
    $account = new Account;
    $account->setRelation('plan', $plan);

    expect((new SocialAccountLimit)->resolve($account))->toBe(30);
});

test('falls back to 5 when no plan', function () {
    $account = new Account;
    $account->setRelation('plan', null);

    expect((new SocialAccountLimit)->resolve($account))->toBe(5);
});
