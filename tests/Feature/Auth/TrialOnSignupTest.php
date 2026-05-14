<?php

declare(strict_types=1);

use App\Actions\User\CreateUser;
use App\Enums\Plan\Slug;
use App\Models\Plan;
use Carbon\Carbon;
use Database\Seeders\PlanSeeder;

beforeEach(function () {
    config(['trypost.self_hosted' => false]);
    $this->seed(PlanSeeder::class);
});

test('new signup gets a 7-day trial without card', function () {
    Carbon::setTestNow('2026-05-14 12:00:00');

    $user = CreateUser::execute([
        'name' => 'Alice',
        'email' => 'alice@example.com',
        'password' => 'password123',
        'timezone' => 'UTC',
        'registration_ip' => '127.0.0.1',
    ]);

    $starterPlan = Plan::where('slug', Slug::Starter)->firstOrFail();

    expect($user->account->plan_id)->toBe($starterPlan->id);
    expect($user->account->trial_ends_at?->toDateTimeString())->toBe('2026-05-22 12:00:00');
    expect($user->account->stripe_id)->toBeNull();
});

test('account during generic trial is recognized as on trial', function () {
    Carbon::setTestNow('2026-05-14 12:00:00');

    $user = CreateUser::execute([
        'name' => 'Alice',
        'email' => 'alice2@example.com',
        'password' => 'password123',
        'timezone' => 'UTC',
        'registration_ip' => '127.0.0.1',
    ]);

    expect($user->account->isOnTrial())->toBeTrue();
    expect($user->account->onGenericTrial())->toBeTrue();
});

test('account whose generic trial expired is not on trial', function () {
    Carbon::setTestNow('2026-05-14 12:00:00');

    $user = CreateUser::execute([
        'name' => 'Alice',
        'email' => 'alice3@example.com',
        'password' => 'password123',
        'timezone' => 'UTC',
        'registration_ip' => '127.0.0.1',
    ]);

    Carbon::setTestNow('2026-05-22 12:01:00');

    expect($user->account->fresh()->isOnTrial())->toBeFalse();
});
