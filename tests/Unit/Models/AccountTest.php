<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\Plan;

test('hasUsableAccess returns true when account has a plan_id', function () {
    config(['trypost.self_hosted' => false]);
    $plan = Plan::first();
    $account = Account::factory()->create(['plan_id' => $plan->id]);

    expect($account->hasUsableAccess())->toBeTrue();
});

test('hasUsableAccess returns false when account has no plan_id', function () {
    config(['trypost.self_hosted' => false]);
    $account = Account::factory()->create(['plan_id' => null]);

    expect($account->hasUsableAccess())->toBeFalse();
});

test('hasUsableAccess returns true in self-hosted mode regardless of plan', function () {
    config(['trypost.self_hosted' => true]);
    $account = Account::factory()->create(['plan_id' => null]);

    expect($account->hasUsableAccess())->toBeTrue();
});
