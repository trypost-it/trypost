<?php

declare(strict_types=1);

use App\Enums\Plan\Slug;
use App\Features\BlockedNetworks;
use App\Features\CanUseAi;
use App\Features\CanUseAnalytics;
use App\Models\Account;
use App\Models\Plan;
use Database\Seeders\PlanSeeder;
use Laravel\Pennant\Feature;

beforeEach(function () {
    $this->seed(PlanSeeder::class);
});

test('CanUseAi resolves false for free plan', function () {
    $freePlan = Plan::where('slug', Slug::Free)->firstOrFail();
    $account = Account::factory()->create(['plan_id' => $freePlan->id]);

    expect(Feature::for($account)->value(CanUseAi::class))->toBeFalse();
});

test('CanUseAi resolves true for paid plans', function () {
    $starterPlan = Plan::where('slug', Slug::Starter)->firstOrFail();
    $account = Account::factory()->create(['plan_id' => $starterPlan->id]);

    expect(Feature::for($account)->value(CanUseAi::class))->toBeTrue();
});

test('CanUseAnalytics resolves false for free plan', function () {
    $freePlan = Plan::where('slug', Slug::Free)->firstOrFail();
    $account = Account::factory()->create(['plan_id' => $freePlan->id]);

    expect(Feature::for($account)->value(CanUseAnalytics::class))->toBeFalse();
});

test('CanUseAnalytics resolves true for paid plans', function () {
    $proPlan = Plan::where('slug', Slug::Pro)->firstOrFail();
    $account = Account::factory()->create(['plan_id' => $proPlan->id]);

    expect(Feature::for($account)->value(CanUseAnalytics::class))->toBeTrue();
});

test('BlockedNetworks returns ["x"] for free plan', function () {
    $freePlan = Plan::where('slug', Slug::Free)->firstOrFail();
    $account = Account::factory()->create(['plan_id' => $freePlan->id]);

    expect(Feature::for($account)->value(BlockedNetworks::class))->toBe(['x']);
});

test('BlockedNetworks returns empty array for paid plans', function () {
    $maxPlan = Plan::where('slug', Slug::Max)->firstOrFail();
    $account = Account::factory()->create(['plan_id' => $maxPlan->id]);

    expect(Feature::for($account)->value(BlockedNetworks::class))->toBe([]);
});
