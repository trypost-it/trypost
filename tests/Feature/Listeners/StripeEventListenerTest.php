<?php

declare(strict_types=1);

use App\Enums\User\Setup;
use App\Events\SubscriptionCreated;
use App\Listeners\StripeEventListener;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Events\WebhookReceived;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create([
        'user_id' => $this->user->id,
        'stripe_id' => 'cus_test123',
    ]);

    $this->listener = new StripeEventListener;
});

// ========================================
// customer.subscription.created
// ========================================

test('subscription created dispatches event', function () {
    Event::fake([SubscriptionCreated::class]);

    $this->listener->handle(new WebhookReceived([
        'type' => 'customer.subscription.created',
        'data' => ['object' => ['customer' => 'cus_test123', 'id' => 'sub_123']],
    ]));

    Event::assertDispatched(SubscriptionCreated::class, fn ($e) => $e->workspace->id === $this->workspace->id);
});

test('subscription created marks setup as completed when on subscription step', function () {
    Event::fake([SubscriptionCreated::class]);
    $this->user->update(['setup' => Setup::Subscription]);

    $this->listener->handle(new WebhookReceived([
        'type' => 'customer.subscription.created',
        'data' => ['object' => ['customer' => 'cus_test123', 'id' => 'sub_123']],
    ]));

    expect($this->user->fresh()->setup)->toBe(Setup::Completed);
});

test('subscription created does not change setup if already completed', function () {
    Event::fake([SubscriptionCreated::class]);
    $this->user->update(['setup' => Setup::Completed]);

    $this->listener->handle(new WebhookReceived([
        'type' => 'customer.subscription.created',
        'data' => ['object' => ['customer' => 'cus_test123', 'id' => 'sub_123']],
    ]));

    expect($this->user->fresh()->setup)->toBe(Setup::Completed);
});

test('subscription created does not skip onboarding steps', function () {
    Event::fake([SubscriptionCreated::class]);
    $this->user->update(['setup' => Setup::Role]);

    $this->listener->handle(new WebhookReceived([
        'type' => 'customer.subscription.created',
        'data' => ['object' => ['customer' => 'cus_test123', 'id' => 'sub_123']],
    ]));

    expect($this->user->fresh()->setup)->toBe(Setup::Role);
});

test('subscription created does not skip connections step', function () {
    Event::fake([SubscriptionCreated::class]);
    $this->user->update(['setup' => Setup::Connections]);

    $this->listener->handle(new WebhookReceived([
        'type' => 'customer.subscription.created',
        'data' => ['object' => ['customer' => 'cus_test123', 'id' => 'sub_123']],
    ]));

    expect($this->user->fresh()->setup)->toBe(Setup::Connections);
});

// ========================================
// customer.subscription.updated
// ========================================

test('subscription updated handles event without error', function () {
    $this->listener->handle(new WebhookReceived([
        'type' => 'customer.subscription.updated',
        'data' => ['object' => ['customer' => 'cus_test123', 'id' => 'sub_123']],
    ]));

    expect(true)->toBeTrue();
});

// ========================================
// customer.subscription.deleted
// ========================================

test('subscription deleted handles event without error', function () {
    $this->listener->handle(new WebhookReceived([
        'type' => 'customer.subscription.deleted',
        'data' => ['object' => ['customer' => 'cus_test123', 'id' => 'sub_123']],
    ]));

    expect(true)->toBeTrue();
});

// ========================================
// Edge cases — missing/invalid data
// ========================================

test('ignores event without customer id', function () {
    Event::fake([SubscriptionCreated::class]);

    $this->listener->handle(new WebhookReceived([
        'type' => 'customer.subscription.created',
        'data' => ['object' => []],
    ]));

    Event::assertNotDispatched(SubscriptionCreated::class);
});

test('ignores event for unknown customer', function () {
    Event::fake([SubscriptionCreated::class]);

    $this->listener->handle(new WebhookReceived([
        'type' => 'customer.subscription.created',
        'data' => ['object' => ['customer' => 'cus_nonexistent']],
    ]));

    Event::assertNotDispatched(SubscriptionCreated::class);
});

test('ignores unknown event types', function () {
    Event::fake([SubscriptionCreated::class]);

    $this->listener->handle(new WebhookReceived([
        'type' => 'invoice.payment_succeeded',
        'data' => ['object' => ['customer' => 'cus_test123']],
    ]));

    Event::assertNotDispatched(SubscriptionCreated::class);
});

test('ignores event with empty payload', function () {
    Event::fake([SubscriptionCreated::class]);

    $this->listener->handle(new WebhookReceived([]));

    Event::assertNotDispatched(SubscriptionCreated::class);
});

test('ignores event with null type', function () {
    Event::fake([SubscriptionCreated::class]);

    $this->listener->handle(new WebhookReceived([
        'type' => null,
        'data' => ['object' => ['customer' => 'cus_test123']],
    ]));

    Event::assertNotDispatched(SubscriptionCreated::class);
});

// ========================================
// Error handling
// ========================================

test('logs error and does not throw on exception', function () {
    Log::shouldReceive('error')
        ->once()
        ->withArgs(fn ($msg) => str_contains($msg, 'Stripe webhook error'));

    // Force an exception by making Event::dispatch throw
    Event::listen(SubscriptionCreated::class, fn () => throw new Exception('Simulated error'));

    $this->listener->handle(new WebhookReceived([
        'type' => 'customer.subscription.created',
        'data' => ['object' => ['customer' => 'cus_test123']],
    ]));
});

test('handles malformed payload gracefully', function () {
    $this->listener->handle(new WebhookReceived([
        'data' => 'not_an_array',
    ]));

    expect(true)->toBeTrue();
});
