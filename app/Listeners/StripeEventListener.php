<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\SubscriptionCreated;
use App\Models\Account;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Events\WebhookReceived;

class StripeEventListener
{
    public function handle(WebhookReceived $event): void
    {
        try {
            $type = data_get($event->payload, 'type');
            $stripeCustomerId = data_get($event->payload, 'data.object.customer');

            if (! $stripeCustomerId) {
                return;
            }

            $workspace = Account::where('stripe_id', $stripeCustomerId)->first();

            if (! $workspace) {
                return;
            }

            match ($type) {
                'customer.subscription.created' => $this->handleSubscriptionCreated($workspace, $event->payload),
                'customer.subscription.updated' => $this->handleSubscriptionUpdated($workspace, $event->payload),
                'customer.subscription.deleted' => $this->handleSubscriptionDeleted($workspace, $event->payload),
                default => null,
            };
        } catch (\Exception $e) {
            Log::error('Stripe webhook error: '.$e->getMessage(), [
                'exception' => $e,
                'payload' => $event->payload,
            ]);
        }
    }

    protected function handleSubscriptionCreated(Account $account, array $payload): void
    {
        SubscriptionCreated::dispatch($account);
    }

    protected function handleSubscriptionUpdated(Account $workspace, array $payload): void
    {
        //
    }

    protected function handleSubscriptionDeleted(Account $workspace, array $payload): void
    {
        //
    }
}
