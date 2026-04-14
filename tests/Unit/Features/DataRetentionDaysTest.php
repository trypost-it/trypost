<?php

declare(strict_types=1);

use App\Features\DataRetentionDays;
use App\Models\Plan;
use App\Models\Workspace;

test('returns plan data retention days', function () {
    $plan = new Plan(['data_retention_days' => 365]);
    $workspace = new Workspace;
    $workspace->setRelation('plan', $plan);

    expect((new DataRetentionDays)->resolve($workspace))->toBe(365);
});

test('falls back to 30 when no plan', function () {
    $workspace = new Workspace;
    $workspace->setRelation('plan', null);

    expect((new DataRetentionDays)->resolve($workspace))->toBe(30);
});
