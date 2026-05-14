<?php

declare(strict_types=1);

use App\Enums\Plan\Slug;
use App\Models\Plan;

test('plan casts new feature columns correctly', function () {
    $plan = Plan::where('slug', Slug::Free)->firstOrFail();
    $plan->update([
        'allowed_networks' => ['linkedin', 'instagram'],
        'can_use_ai' => false,
        'can_use_analytics' => false,
        'scheduled_posts_limit' => 15,
    ]);

    $plan->refresh();

    expect($plan->allowed_networks)->toBe(['linkedin', 'instagram']);
    expect($plan->can_use_ai)->toBeFalse();
    expect($plan->can_use_analytics)->toBeFalse();
    expect($plan->scheduled_posts_limit)->toBe(15);
});
