<?php

declare(strict_types=1);

use App\Features\AiVideosLimit;
use App\Models\Account;
use App\Models\Plan;

test('returns plan ai videos limit', function () {
    $plan = new Plan(['ai_videos_limit' => 50]);
    $account = new Account;
    $account->setRelation('plan', $plan);

    expect((new AiVideosLimit)->resolve($account))->toBe(50);
});

test('falls back to 10 when no plan', function () {
    $account = new Account;
    $account->setRelation('plan', null);

    expect((new AiVideosLimit)->resolve($account))->toBe(10);
});
