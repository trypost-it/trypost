<?php

declare(strict_types=1);

use App\Enums\Plan\Slug;
use App\Features\ScheduledPostsLimit;
use App\Models\Account;
use App\Models\Plan;
use Database\Seeders\PlanSeeder;
use Laravel\Pennant\Feature;

beforeEach(function () {
    $this->seed(PlanSeeder::class);
});

test('scheduled posts limit resolves to plan value for free', function () {
    $freePlan = Plan::where('slug', Slug::Free)->firstOrFail();
    $account = Account::factory()->create(['plan_id' => $freePlan->id]);

    $value = Feature::for($account)->value(ScheduledPostsLimit::class);

    expect($value)->toBe(15);
});

test('scheduled posts limit resolves to null (unlimited) for paid', function () {
    $proPlan = Plan::where('slug', Slug::Pro)->firstOrFail();
    $account = Account::factory()->create(['plan_id' => $proPlan->id]);

    $value = Feature::for($account)->value(ScheduledPostsLimit::class);

    expect($value)->toBeNull();
});
