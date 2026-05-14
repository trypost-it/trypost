<?php

declare(strict_types=1);

use App\Enums\Plan\Slug;
use App\Models\Account;
use App\Models\Plan;
use Database\Seeders\PlanSeeder;

beforeEach(function () {
    $this->seed(PlanSeeder::class);
});

test('backfills accounts with null plan_id to free', function () {
    $freePlan = Plan::where('slug', Slug::Free)->firstOrFail();

    $a = Account::factory()->create();
    $a->update(['plan_id' => null]);
    $b = Account::factory()->create();
    $b->update(['plan_id' => null]);

    $this->artisan('accounts:backfill-free-plan')
        ->expectsOutput('Found 2 account(s) with NULL plan_id.')
        ->expectsOutput('Updated 2 account(s) to the Free plan.')
        ->assertSuccessful();

    expect($a->fresh()->plan_id)->toBe($freePlan->id);
    expect($b->fresh()->plan_id)->toBe($freePlan->id);
});

test('dry-run does not modify data', function () {
    $a = Account::factory()->create();
    $a->update(['plan_id' => null]);

    $this->artisan('accounts:backfill-free-plan', ['--dry-run' => true])
        ->expectsOutput('Found 1 account(s) with NULL plan_id.')
        ->assertSuccessful();

    expect($a->fresh()->plan_id)->toBeNull();
});

test('no-op when no null plan_id accounts', function () {
    $this->artisan('accounts:backfill-free-plan')
        ->expectsOutput('No accounts with NULL plan_id. Nothing to do.')
        ->assertSuccessful();
});

test('errors when free plan is missing', function () {
    Plan::where('slug', Slug::Free)->delete();

    $a = Account::factory()->create();
    $a->update(['plan_id' => null]);

    $this->artisan('accounts:backfill-free-plan')
        ->assertFailed();
});
