<?php

declare(strict_types=1);

use App\Features\BrandLimit;
use App\Models\Plan;
use App\Models\Workspace;

test('returns plan brand limit', function () {
    $plan = new Plan(['brand_limit' => 5]);
    $workspace = new Workspace;
    $workspace->setRelation('plan', $plan);

    expect((new BrandLimit)->resolve($workspace))->toBe(5);
});

test('falls back to 0 when no plan', function () {
    $workspace = new Workspace;
    $workspace->setRelation('plan', null);

    expect((new BrandLimit)->resolve($workspace))->toBe(0);
});
