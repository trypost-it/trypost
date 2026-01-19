<?php

use App\Events\SubscriptionCreated;
use App\Listeners\StripeEventListener;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Laravel\Cashier\Events\WebhookReceived;

test('listener dispatches subscription created event', function () {
    Event::fake([SubscriptionCreated::class]);

    $user = User::factory()->create([
        'stripe_id' => 'cus_123',
    ]);

    $listener = new StripeEventListener;
    $listener->handle(new WebhookReceived([
        'type' => 'customer.subscription.created',
        'data' => [
            'object' => [
                'customer' => 'cus_123',
            ],
        ],
    ]));

    Event::assertDispatched(SubscriptionCreated::class, function ($event) use ($user) {
        return $event->user->id === $user->id;
    });
});

test('listener handles subscription updated event', function () {
    $user = User::factory()->create([
        'stripe_id' => 'cus_123',
    ]);

    $listener = new StripeEventListener;

    // Should not throw any error
    $listener->handle(new WebhookReceived([
        'type' => 'customer.subscription.updated',
        'data' => [
            'object' => [
                'customer' => 'cus_123',
            ],
        ],
    ]));

    expect(true)->toBeTrue();
});

test('listener handles subscription deleted event', function () {
    $user = User::factory()->create([
        'stripe_id' => 'cus_123',
    ]);

    $listener = new StripeEventListener;

    // Should not throw any error
    $listener->handle(new WebhookReceived([
        'type' => 'customer.subscription.deleted',
        'data' => [
            'object' => [
                'customer' => 'cus_123',
            ],
        ],
    ]));

    expect(true)->toBeTrue();
});

test('listener ignores events without customer id', function () {
    Event::fake([SubscriptionCreated::class]);

    $listener = new StripeEventListener;
    $listener->handle(new WebhookReceived([
        'type' => 'customer.subscription.created',
        'data' => [
            'object' => [],
        ],
    ]));

    Event::assertNotDispatched(SubscriptionCreated::class);
});

test('listener ignores events for unknown customers', function () {
    Event::fake([SubscriptionCreated::class]);

    $listener = new StripeEventListener;
    $listener->handle(new WebhookReceived([
        'type' => 'customer.subscription.created',
        'data' => [
            'object' => [
                'customer' => 'cus_unknown',
            ],
        ],
    ]));

    Event::assertNotDispatched(SubscriptionCreated::class);
});

test('listener handles unknown event types gracefully', function () {
    $user = User::factory()->create([
        'stripe_id' => 'cus_123',
    ]);

    $listener = new StripeEventListener;

    // Should not throw any error
    $listener->handle(new WebhookReceived([
        'type' => 'unknown.event.type',
        'data' => [
            'object' => [
                'customer' => 'cus_123',
            ],
        ],
    ]));

    expect(true)->toBeTrue();
});
