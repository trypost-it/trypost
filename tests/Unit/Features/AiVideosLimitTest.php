<?php

declare(strict_types=1);

use App\Features\AiVideosLimit;
use App\Models\Plan;
use App\Models\Workspace;

test('returns plan ai videos limit', function () {
    $plan = new Plan(['ai_videos_limit' => 50]);
    $workspace = new Workspace;
    $workspace->setRelation('plan', $plan);

    expect((new AiVideosLimit)->resolve($workspace))->toBe(50);
});

test('falls back to 10 when no plan', function () {
    $workspace = new Workspace;
    $workspace->setRelation('plan', null);

    expect((new AiVideosLimit)->resolve($workspace))->toBe(10);
});
