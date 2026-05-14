<?php

declare(strict_types=1);

use App\Enums\Plan\Slug;
use App\Models\Account;
use App\Models\Plan;
use Carbon\Carbon;
use Database\Seeders\PlanSeeder;

beforeEach(function () {
    $this->seed(PlanSeeder::class);
    Carbon::setTestNow('2026-05-14 12:00:00');
});

test('isOnTrial returns true for account on generic trial', function () {
    $account = Account::factory()->create([
        'trial_ends_at' => now()->addDays(7),
    ]);

    expect($account->isOnTrial())->toBeTrue();
});

test('isOnTrial returns false when generic trial has expired and there is no subscription', function () {
    $account = Account::factory()->create([
        'trial_ends_at' => now()->subDay(),
    ]);

    expect($account->isOnTrial())->toBeFalse();
});

test('isOnTrial returns false for account without trial or subscription', function () {
    $account = Account::factory()->create(['trial_ends_at' => null]);

    expect($account->isOnTrial())->toBeFalse();
});

test('isOnTrial returns true via subscription trial when account has no generic trial', function () {
    $account = Account::factory()->create([
        'trial_ends_at' => null,
        'stripe_id' => 'cus_test_'.fake()->uuid(),
    ]);
    $account->subscriptions()->create([
        'type' => Account::SUBSCRIPTION_NAME,
        'stripe_id' => 'sub_test_'.fake()->uuid(),
        'stripe_status' => 'trialing',
        'stripe_price' => 'price_123',
        'trial_ends_at' => now()->addDays(5),
    ]);

    expect($account->isOnTrial())->toBeTrue();
});

test('activeTrialEndsAt returns null when not on any trial', function () {
    $account = Account::factory()->create(['trial_ends_at' => null]);

    expect($account->activeTrialEndsAt())->toBeNull();
});

test('activeTrialEndsAt returns generic trial date when only generic is active', function () {
    $endsAt = now()->addDays(7);
    $account = Account::factory()->create(['trial_ends_at' => $endsAt]);

    expect($account->activeTrialEndsAt()?->toDateTimeString())
        ->toBe($endsAt->toDateTimeString());
});

test('activeTrialEndsAt returns subscription date when only subscription trial is active', function () {
    $subscriptionEndsAt = now()->addDays(5);
    $account = Account::factory()->create([
        'trial_ends_at' => null,
        'stripe_id' => 'cus_test_'.fake()->uuid(),
    ]);
    $account->subscriptions()->create([
        'type' => Account::SUBSCRIPTION_NAME,
        'stripe_id' => 'sub_test_'.fake()->uuid(),
        'stripe_status' => 'trialing',
        'stripe_price' => 'price_123',
        'trial_ends_at' => $subscriptionEndsAt,
    ]);

    expect($account->activeTrialEndsAt()?->toDateTimeString())
        ->toBe($subscriptionEndsAt->toDateTimeString());
});

test('activeTrialEndsAt prefers subscription date over generic when both active', function () {
    $genericEndsAt = now()->addDays(7);
    $subscriptionEndsAt = now()->addDays(14);

    $account = Account::factory()->create([
        'trial_ends_at' => $genericEndsAt,
        'stripe_id' => 'cus_test_'.fake()->uuid(),
    ]);
    $account->subscriptions()->create([
        'type' => Account::SUBSCRIPTION_NAME,
        'stripe_id' => 'sub_test_'.fake()->uuid(),
        'stripe_status' => 'trialing',
        'stripe_price' => 'price_123',
        'trial_ends_at' => $subscriptionEndsAt,
    ]);

    expect($account->activeTrialEndsAt()?->toDateTimeString())
        ->toBe($subscriptionEndsAt->toDateTimeString());
});

test('activeTrialEndsAt returns null for paying customer post-trial', function () {
    $account = Account::factory()->create([
        'trial_ends_at' => null,
        'stripe_id' => 'cus_test_'.fake()->uuid(),
        'plan_id' => Plan::where('slug', Slug::Starter)->value('id'),
    ]);
    $account->subscriptions()->create([
        'type' => Account::SUBSCRIPTION_NAME,
        'stripe_id' => 'sub_test_'.fake()->uuid(),
        'stripe_status' => 'active',
        'stripe_price' => 'price_123',
        'trial_ends_at' => null,
    ]);

    expect($account->activeTrialEndsAt())->toBeNull();
    expect($account->isOnTrial())->toBeFalse();
});
