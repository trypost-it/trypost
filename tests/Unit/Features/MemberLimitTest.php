<?php

declare(strict_types=1);

use App\Features\MemberLimit;
use App\Models\Plan;
use App\Models\Workspace;

test('returns plan member limit', function () {
    $plan = new Plan(['member_limit' => 10]);
    $workspace = new Workspace;
    $workspace->setRelation('plan', $plan);

    expect((new MemberLimit)->resolve($workspace))->toBe(10);
});

test('falls back to 1 when no plan', function () {
    $workspace = new Workspace;
    $workspace->setRelation('plan', null);

    expect((new MemberLimit)->resolve($workspace))->toBe(1);
});
