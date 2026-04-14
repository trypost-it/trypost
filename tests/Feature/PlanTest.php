<?php

declare(strict_types=1);

use App\Enums\Plan\Slug;
use App\Models\Plan;

test('plan can be created with factory', function () {
    $plan = Plan::factory()->create([
        'slug' => Slug::Pro,
        'name' => 'Pro',
        'monthly_price' => 3900,
        'yearly_price' => 39000,
    ]);

    expect($plan)->toBeInstanceOf(Plan::class)
        ->and($plan->slug)->toBe(Slug::Pro)
        ->and($plan->name)->toBe('Pro')
        ->and($plan->monthly_price)->toBe(3900)
        ->and($plan->yearly_price)->toBe(39000)
        ->and($plan->is_archived)->toBeFalse();
});

test('plan slug is cast to enum', function () {
    $plan = Plan::factory()->create(['slug' => Slug::Starter]);

    expect($plan->slug)->toBeInstanceOf(Slug::class)
        ->and($plan->slug)->toBe(Slug::Starter)
        ->and($plan->slug->label())->toBe('Starter');
});

test('active scope excludes archived plans', function () {
    Plan::factory()->create(['slug' => Slug::Starter, 'is_archived' => false]);
    Plan::factory()->create(['slug' => Slug::Plus, 'is_archived' => false]);
    Plan::factory()->archived()->create(['slug' => Slug::Pro]);

    $activePlans = Plan::active()->get();

    expect($activePlans)->toHaveCount(2);
});

test('formatted monthly price returns dollar format', function () {
    $plan = Plan::factory()->create(['monthly_price' => 1900]);

    expect($plan->formattedMonthlyPrice())->toBe('$19');
});

test('formatted yearly price returns dollar format', function () {
    $plan = Plan::factory()->create(['yearly_price' => 19000]);

    expect($plan->formattedYearlyPrice())->toBe('$190');
});

test('integer fields are cast correctly', function () {
    $plan = Plan::factory()->create([
        'social_account_limit' => 10,
        'member_limit' => 3,
        'brand_limit' => 5,
        'ai_images_limit' => 50,
        'ai_videos_limit' => 25,
        'data_retention_days' => 365,
        'sort' => 2,
    ]);

    expect($plan->social_account_limit)->toBeInt()
        ->and($plan->member_limit)->toBeInt()
        ->and($plan->brand_limit)->toBeInt()
        ->and($plan->ai_images_limit)->toBeInt()
        ->and($plan->ai_videos_limit)->toBeInt()
        ->and($plan->data_retention_days)->toBeInt()
        ->and($plan->sort)->toBeInt();
});
