<?php

declare(strict_types=1);

use App\Features\SocialAccountLimit;
use App\Models\Plan;
use App\Models\Workspace;

test('returns plan social account limit', function () {
    $plan = new Plan(['social_account_limit' => 30]);
    $workspace = new Workspace;
    $workspace->setRelation('plan', $plan);

    expect((new SocialAccountLimit)->resolve($workspace))->toBe(30);
});

test('falls back to 5 when no plan', function () {
    $workspace = new Workspace;
    $workspace->setRelation('plan', null);

    expect((new SocialAccountLimit)->resolve($workspace))->toBe(5);
});
