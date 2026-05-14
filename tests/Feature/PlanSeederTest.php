<?php

declare(strict_types=1);

use App\Enums\Plan\Slug;
use App\Models\Plan;
use Database\Seeders\PlanSeeder;

test('seeder creates 5 plans', function () {
    expect(Plan::count())->toBe(5);
});

test('seeder creates plans with correct limits', function () {
    $starter = Plan::where('slug', Slug::Starter)->first();

    expect($starter->name)->toBe('Starter')
        ->and($starter->social_account_limit)->toBe(5)
        ->and($starter->member_limit)->toBe(1)
        ->and($starter->workspace_limit)->toBe(1)
        ->and($starter->monthly_credits_limit)->toBe(1000)
        ->and($starter->sort)->toBe(1);

    $max = Plan::where('slug', Slug::Max)->first();

    expect($max->name)->toBe('Max')
        ->and($max->social_account_limit)->toBe(100)
        ->and($max->member_limit)->toBe(20)
        ->and($max->workspace_limit)->toBe(50)
        ->and($max->monthly_credits_limit)->toBe(15000)
        ->and($max->sort)->toBe(4);
});

test('seeder is idempotent', function () {
    $this->seed(PlanSeeder::class);

    expect(Plan::count())->toBe(5);
});

test('active scope excludes archived plans', function () {
    $plan = Plan::where('slug', Slug::Starter)->first();
    $plan->update(['is_archived' => true]);

    expect(Plan::active()->count())->toBe(4)
        ->and(Plan::count())->toBe(5);
});
