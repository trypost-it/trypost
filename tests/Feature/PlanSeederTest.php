<?php

declare(strict_types=1);

use App\Enums\Plan\Slug;
use App\Models\Plan;
use Database\Seeders\PlanSeeder;

test('seeder creates 4 plans', function () {
    expect(Plan::count())->toBe(4);
});

test('seeder creates plans with correct limits', function () {
    $starter = Plan::where('slug', Slug::Starter)->first();

    expect($starter->name)->toBe('Starter')
        ->and($starter->monthly_price)->toBe(1900)
        ->and($starter->yearly_price)->toBe(19000)
        ->and($starter->social_account_limit)->toBe(5)
        ->and($starter->member_limit)->toBe(1)
        ->and($starter->workspace_limit)->toBe(1)
        ->and($starter->ai_images_limit)->toBe(50)
        ->and($starter->ai_videos_limit)->toBe(10)
        ->and($starter->data_retention_days)->toBe(30)
        ->and($starter->sort)->toBe(1);

    $max = Plan::where('slug', Slug::Max)->first();

    expect($max->name)->toBe('Max')
        ->and($max->monthly_price)->toBe(9900)
        ->and($max->yearly_price)->toBe(99000)
        ->and($max->social_account_limit)->toBe(100)
        ->and($max->member_limit)->toBe(20)
        ->and($max->workspace_limit)->toBe(50)
        ->and($max->ai_images_limit)->toBe(2000)
        ->and($max->ai_videos_limit)->toBe(500)
        ->and($max->data_retention_days)->toBe(730)
        ->and($max->sort)->toBe(4);
});

test('seeder is idempotent', function () {
    $this->seed(PlanSeeder::class);

    expect(Plan::count())->toBe(4);
});

test('active scope excludes archived plans', function () {
    $plan = Plan::where('slug', Slug::Starter)->first();
    $plan->update(['is_archived' => true]);

    expect(Plan::active()->count())->toBe(3)
        ->and(Plan::count())->toBe(4);
});
