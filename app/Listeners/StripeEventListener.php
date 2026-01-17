<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\SubscriptionCreated;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Events\WebhookReceived;

class StripeEventListener
{
    /**
     * Handle received Stripe webhooks.
     */
    public function handle(WebhookReceived $event): void
    {
        try {
            $type = $event->payload['type'] ?? null;
            $stripeCustomerId = $event->payload['data']['object']['customer'] ?? null;

            if (! $stripeCustomerId) {
                return;
            }

            $user = User::where('stripe_id', $stripeCustomerId)->first();

            if (! $user) {
                return;
            }

            match ($type) {
                'customer.subscription.created' => $this->handleSubscriptionCreated($user, $event->payload),
                'customer.subscription.updated' => $this->handleSubscriptionUpdated($user, $event->payload),
                'customer.subscription.deleted' => $this->handleSubscriptionDeleted($user, $event->payload),
                default => null,
            };
        } catch (\Exception $e) {
            Log::error('Stripe webhook error: '.$e->getMessage(), [
                'exception' => $e,
                'payload' => $event->payload,
            ]);
        }
    }

    protected function handleSubscriptionCreated(User $user, array $payload): void
    {
        SubscriptionCreated::dispatch($user);
    }

    protected function handleSubscriptionUpdated(User $user, array $payload): void
    {
        // Future: dispatch SubscriptionUpdated event if needed
    }

    protected function handleSubscriptionDeleted(User $user, array $payload): void
    {
        // Future: dispatch SubscriptionDeleted event if needed
    }
}
