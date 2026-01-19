<?php

use App\Events\SubscriptionCreated;
use App\Listeners\StripeEventListener;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Laravel\Cashier\Events\WebhookReceived;

beforeEach(function () {
    $this->user = User::factory()->create([
        'stripe_id' => 'cus_test123',
    ]);
});

test('stripe listener handles subscription created event', function () {
    Event::fake([SubscriptionCreated::class]);

    $payload = [
        'type' => 'customer.subscription.created',
        'data' => [
            'object' => [
                'customer' => 'cus_test123',
                'id' => 'sub_test123',
            ],
        ],
    ];

    $event = new WebhookReceived($payload);
    $listener = new StripeEventListener;
    $listener->handle($event);

    Event::assertDispatched(SubscriptionCreated::class, function ($event) {
        return $event->user->id === $this->user->id;
    });
});

test('stripe listener handles subscription updated event', function () {
    $payload = [
        'type' => 'customer.subscription.updated',
        'data' => [
            'object' => [
                'customer' => 'cus_test123',
                'id' => 'sub_test123',
            ],
        ],
    ];

    $event = new WebhookReceived($payload);
    $listener = new StripeEventListener;

    // Should not throw exception
    $listener->handle($event);

    expect(true)->toBeTrue();
});

test('stripe listener handles subscription deleted event', function () {
    $payload = [
        'type' => 'customer.subscription.deleted',
        'data' => [
            'object' => [
                'customer' => 'cus_test123',
                'id' => 'sub_test123',
            ],
        ],
    ];

    $event = new WebhookReceived($payload);
    $listener = new StripeEventListener;

    // Should not throw exception
    $listener->handle($event);

    expect(true)->toBeTrue();
});

test('stripe listener ignores unknown event types', function () {
    Event::fake([SubscriptionCreated::class]);

    $payload = [
        'type' => 'unknown.event.type',
        'data' => [
            'object' => [
                'customer' => 'cus_test123',
            ],
        ],
    ];

    $event = new WebhookReceived($payload);
    $listener = new StripeEventListener;
    $listener->handle($event);

    Event::assertNotDispatched(SubscriptionCreated::class);
});

test('stripe listener ignores events without customer id', function () {
    Event::fake([SubscriptionCreated::class]);

    $payload = [
        'type' => 'customer.subscription.created',
        'data' => [
            'object' => [],
        ],
    ];

    $event = new WebhookReceived($payload);
    $listener = new StripeEventListener;
    $listener->handle($event);

    Event::assertNotDispatched(SubscriptionCreated::class);
});

test('stripe listener ignores events for unknown customers', function () {
    Event::fake([SubscriptionCreated::class]);

    $payload = [
        'type' => 'customer.subscription.created',
        'data' => [
            'object' => [
                'customer' => 'cus_unknown',
            ],
        ],
    ];

    $event = new WebhookReceived($payload);
    $listener = new StripeEventListener;
    $listener->handle($event);

    Event::assertNotDispatched(SubscriptionCreated::class);
});

test('stripe listener handles exceptions gracefully', function () {
    $payload = [
        'type' => 'customer.subscription.created',
        'data' => [
            'object' => [
                'customer' => 'cus_test123',
            ],
        ],
    ];

    // Delete user to cause an issue
    $this->user->delete();

    $event = new WebhookReceived($payload);
    $listener = new StripeEventListener;

    // Should not throw exception
    $listener->handle($event);

    expect(true)->toBeTrue();
});
