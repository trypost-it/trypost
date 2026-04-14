<?php

declare(strict_types=1);

use App\Features\AiImagesLimit;
use App\Models\Plan;
use App\Models\Workspace;

test('returns plan ai images limit', function () {
    $plan = new Plan(['ai_images_limit' => 200]);
    $workspace = new Workspace;
    $workspace->setRelation('plan', $plan);

    expect((new AiImagesLimit)->resolve($workspace))->toBe(200);
});

test('falls back to 50 when no plan', function () {
    $workspace = new Workspace;
    $workspace->setRelation('plan', null);

    expect((new AiImagesLimit)->resolve($workspace))->toBe(50);
});
