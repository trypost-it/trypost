<?php

declare(strict_types=1);

use App\Features\AiImagesLimit;
use App\Models\Account;
use App\Models\Plan;

test('returns plan ai images limit', function () {
    $plan = new Plan(['ai_images_limit' => 200]);
    $account = new Account;
    $account->setRelation('plan', $plan);

    expect((new AiImagesLimit)->resolve($account))->toBe(200);
});

test('falls back to 50 when no plan', function () {
    $account = new Account;
    $account->setRelation('plan', null);

    expect((new AiImagesLimit)->resolve($account))->toBe(50);
});
