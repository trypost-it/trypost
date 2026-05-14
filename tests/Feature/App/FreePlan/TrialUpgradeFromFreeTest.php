<?php

declare(strict_types=1);

use App\Enums\Plan\Slug;
use App\Listeners\StripeEventListener;
use App\Models\Account;
use App\Models\Plan;
use Database\Seeders\PlanSeeder;
use Laravel\Cashier\Events\WebhookReceived;

beforeEach(function () {
    $this->seed(PlanSeeder::class);
});

test('cancelled subscription drops account back to free plan', function () {
    $freePlan = Plan::where('slug', Slug::Free)->firstOrFail();
    $proPlan = Plan::where('slug', Slug::Pro)->firstOrFail();

    $account = Account::factory()->create([
        'plan_id' => $proPlan->id,
        'stripe_id' => 'cus_test_'.fake()->uuid(),
    ]);

    $listener = app(StripeEventListener::class);
    $listener->handle(new WebhookReceived([
        'type' => 'customer.subscription.deleted',
        'data' => ['object' => [
            'customer' => $account->stripe_id,
        ]],
    ]));

    expect($account->fresh()->plan_id)->toBe($freePlan->id);
});
