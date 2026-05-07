<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\SubscriptionCreated;
use App\Jobs\SyncUserToPostHog;
use App\Models\Account;
use App\Services\PostHogService;
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

            $account = Account::where('stripe_id', $stripeCustomerId)->first();

            if (! $account) {
                return;
            }

            match ($type) {
                'customer.subscription.created' => $this->handleSubscriptionCreated($account, $event->payload),
                'customer.subscription.updated' => $this->handleSubscriptionUpdated($account, $event->payload),
                'customer.subscription.deleted' => $this->handleSubscriptionDeleted($account, $event->payload),
                default => null,
            };
        } catch (\Exception $e) {
            Log::error('Stripe webhook error: '.$e->getMessage(), [
                'exception' => $e,
                'payload' => $event->payload,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function handleSubscriptionCreated(Account $account, array $payload): void
    {
        SubscriptionCreated::dispatch($account);

        $this->trackBilling($account, 'subscription.created', $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function handleSubscriptionUpdated(Account $account, array $payload): void
    {
        $this->trackBilling($account, 'subscription.updated', $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function handleSubscriptionDeleted(Account $account, array $payload): void
    {
        $this->trackBilling($account, 'subscription.cancelled', $payload);
    }

    /**
     * Capture the lifecycle event on the account owner's profile and trigger
     * a fresh `SyncUserToPostHog` so the account group properties (plan,
     * has_active_subscription, is_on_trial) reflect the new Stripe state.
     *
     * @param  array<string, mixed>  $payload
     */
    private function trackBilling(Account $account, string $event, array $payload): void
    {
        if (! $account->owner_id) {
            return;
        }

        $properties = [
            'stripe_status' => data_get($payload, 'data.object.status'),
            'plan' => $account->plan?->name,
            'plan_slug' => $account->plan?->slug,
        ];

        app(PostHogService::class)->capture(
            (string) $account->owner_id,
            $event,
            $properties,
            $account,
        );

        SyncUserToPostHog::dispatch((string) $account->owner_id);
    }
}
