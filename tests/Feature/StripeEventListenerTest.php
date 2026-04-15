<?php

declare(strict_types=1);

use App\Enums\User\Setup;
use App\Enums\UserWorkspace\Role;
use App\Listeners\StripeEventListener;
use App\Models\Account;
use App\Models\User;
use App\Models\Workspace;
use Laravel\Cashier\Events\WebhookReceived;

beforeEach(function () {
    config(['trypost.self_hosted' => true]);

    $this->account = Account::factory()->create([
        'stripe_id' => 'cus_test_'.fake()->uuid(),
    ]);
    $this->user = User::factory()->create([
        'setup' => Setup::Subscription,
        'account_id' => $this->account->id,
    ]);
    $this->account->update(['owner_id' => $this->user->id]);
    $this->workspace = Workspace::factory()->create([
        'user_id' => $this->user->id,
        'account_id' => $this->account->id,
    ]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Member->value]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);
});

test('webhook creates subscription and completes setup', function () {
    $payload = [
        'type' => 'customer.subscription.created',
        'data' => [
            'object' => [
                'customer' => $this->account->stripe_id,
                'id' => 'sub_test_'.fake()->uuid(),
                'status' => 'active',
            ],
        ],
    ];

    $event = new WebhookReceived($payload);

    $listener = new StripeEventListener;
    $listener->handle($event);

    expect($this->user->fresh()->setup)->toBe(Setup::Completed);
});

test('webhook ignores unknown stripe customer', function () {
    $payload = [
        'type' => 'customer.subscription.created',
        'data' => [
            'object' => [
                'customer' => 'cus_unknown_'.fake()->uuid(),
                'id' => 'sub_test_'.fake()->uuid(),
                'status' => 'active',
            ],
        ],
    ];

    $event = new WebhookReceived($payload);

    $listener = new StripeEventListener;
    $listener->handle($event);

    expect($this->user->fresh()->setup)->toBe(Setup::Subscription);
});
