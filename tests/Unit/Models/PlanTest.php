<?php

declare(strict_types=1);

use App\Enums\Plan\Slug;
use App\Models\Plan;
use Database\Seeders\PlanSeeder;

beforeEach(function () {
    $this->seed(PlanSeeder::class);
});

test('plan casts legacy columns correctly', function () {
    $plan = Plan::where('slug', Slug::Free)->firstOrFail();

    expect($plan->slug)->toBe(Slug::Free);
    expect($plan->social_account_limit)->toBeInt();
    expect($plan->member_limit)->toBeInt();
    expect($plan->workspace_limit)->toBeInt();
    expect($plan->monthly_credits_limit)->toBeInt();
    expect($plan->sort)->toBeInt();
    expect($plan->is_archived)->toBeBool();
});
