<?php

declare(strict_types=1);

use App\Actions\User\CreateUser;
use App\Enums\Plan\Slug;
use App\Models\Plan;
use Database\Seeders\PlanSeeder;

beforeEach(function () {
    $this->seed(PlanSeeder::class);
});

test('new signup is assigned to the free plan', function () {
    $user = CreateUser::execute([
        'name' => 'Alice',
        'email' => 'alice@example.com',
        'password' => 'password123',
        'timezone' => 'UTC',
        'registration_ip' => '127.0.0.1',
    ]);

    $freePlan = Plan::where('slug', Slug::Free)->firstOrFail();

    expect($user->account->plan_id)->toBe($freePlan->id);
});
