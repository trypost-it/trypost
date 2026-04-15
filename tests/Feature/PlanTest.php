<?php

declare(strict_types=1);

use App\Enums\Plan\Slug;
use App\Models\Plan;

test('plan can be created with factory', function () {
    Plan::where('slug', Slug::Pro)->delete();

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
    $plan = Plan::where('slug', Slug::Starter)->first();

    expect($plan->slug)->toBeInstanceOf(Slug::class)
        ->and($plan->slug)->toBe(Slug::Starter)
        ->and($plan->slug->label())->toBe('Starter');
});

test('active scope excludes archived plans', function () {
    $activeBefore = Plan::active()->count();

    $plan = Plan::where('slug', Slug::Pro)->first();
    $plan->update(['is_archived' => true]);

    expect(Plan::active()->count())->toBe($activeBefore - 1);
});

test('formatted monthly price returns dollar format', function () {
    $plan = Plan::where('slug', Slug::Starter)->first();

    expect($plan->formattedMonthlyPrice())->toBe('$19');
});

test('formatted yearly price returns dollar format', function () {
    $plan = Plan::where('slug', Slug::Starter)->first();

    expect($plan->formattedYearlyPrice())->toBe('$190');
});

test('integer fields are cast correctly', function () {
    $plan = Plan::where('slug', Slug::Starter)->first();

    expect($plan->social_account_limit)->toBeInt()
        ->and($plan->member_limit)->toBeInt()
        ->and($plan->workspace_limit)->toBeInt()
        ->and($plan->ai_images_limit)->toBeInt()
        ->and($plan->ai_videos_limit)->toBeInt()
        ->and($plan->data_retention_days)->toBeInt()
        ->and($plan->sort)->toBeInt();
});
