<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\SubscriptionCreated;
use App\Jobs\PostHog\TrackBilling;
use App\Models\Account;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Events\WebhookReceived;

class StripeEventListener
{
    private const POSTHOG_EVENT_BY_TYPE = [
        'customer.subscription.created' => 'subscription.created',
        'customer.subscription.updated' => 'subscription.updated',
        'customer.subscription.deleted' => 'subscription.cancelled',
    ];

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

            if ($type === 'customer.subscription.created') {
                SubscriptionCreated::dispatch($account);
            }

            if ($postHogEvent = self::POSTHOG_EVENT_BY_TYPE[$type] ?? null) {
                TrackBilling::dispatch((string) $account->id, $postHogEvent, $event->payload);
            }
        } catch (\Exception $e) {
            Log::error('Stripe webhook error: '.$e->getMessage(), [
                'exception' => $e,
                'payload' => $event->payload,
            ]);
        }
    }
}
