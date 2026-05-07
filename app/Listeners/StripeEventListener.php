<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\PostHog\BillingEvent;
use App\Events\SubscriptionCreated;
use App\Jobs\PostHog\TrackBilling;
use App\Models\Account;
use App\Models\Plan;
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
        $previousPlan = $account->plan?->name;

        if ($plan = $this->resolvePlanFromSubscriptionItems($payload, $account)) {
            $account->update(['plan_id' => $plan->id]);
            $account->forgetPlanFeatureCache();
        }

        SubscriptionCreated::dispatch($account);

        $this->trackPlanChange($account, BillingEvent::Created, $previousPlan, $payload);
    }

    /**
     * Plan changes coming from Stripe (billing portal, dashboard, or our own
     * `BillingController@swap`) re-arrive here. Re-resolving the plan from
     * the price ids guarantees the local state matches Stripe even when a
     * change happens out-of-band.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function handleSubscriptionUpdated(Account $account, array $payload): void
    {
        $previousPlan = $account->plan?->name;

        if ($plan = $this->resolvePlanFromSubscriptionItems($payload, $account)) {
            $account->update(['plan_id' => $plan->id]);
            $account->forgetPlanFeatureCache();
        }

        $this->trackPlanChange($account, BillingEvent::Updated, $previousPlan, $payload);
    }

    /**
     * Subscription fully terminated (period ended after cancellation, or
     * cancelled immediately). Clear the local plan so the UI and
     * authorisation checks (`Account::hasActiveSubscription`) reflect
     * "no plan" instead of keeping the previous one attached.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function handleSubscriptionDeleted(Account $account, array $payload): void
    {
        // Stripe re-delivers webhooks on transient failures; if the plan is
        // already cleared we skip the rest so the cancellation event isn't
        // captured twice and the (idempotent but pointless) cache flush
        // doesn't run again.
        if ($account->plan_id === null) {
            return;
        }

        $previousPlan = $account->plan?->name;

        $account->update(['plan_id' => null]);
        $account->forgetPlanFeatureCache();

        $this->trackPlanChange($account, BillingEvent::Cancelled, $previousPlan, $payload);
    }

    /**
     * Resolve the matching `Plan` for a Stripe subscription payload by
     * looking up the items' price ids against the local plans table.
     * Logs a warning when no match is found so price/plan drift between
     * Stripe and the local DB is visible.
     *
     * @param  array<string, mixed>  $payload
     */
    private function resolvePlanFromSubscriptionItems(array $payload, Account $account): ?Plan
    {
        $priceIds = collect(data_get($payload, 'data.object.items.data', []))
            ->pluck('price.id')
            ->filter()
            ->all();

        if (empty($priceIds)) {
            return null;
        }

        $plan = Plan::query()
            ->where(function ($query) use ($priceIds): void {
                $query->whereIn('stripe_monthly_price_id', $priceIds)
                    ->orWhereIn('stripe_yearly_price_id', $priceIds);
            })
            ->first();

        if (! $plan) {
            Log::warning('Stripe webhook: no matching plan found in subscription items', [
                'account_id' => $account->id,
                'price_ids' => $priceIds,
            ]);
        }

        return $plan;
    }

    /**
     * Hand the lifecycle event off to the queue so the listener stays fast.
     * Skip dispatch when PostHog is disabled — `TrackBilling::handle` would
     * no-op anyway, but enqueuing it still costs queue worker cycles.
     *
     * @param  array<string, mixed>  $payload
     */
    private function trackPlanChange(Account $account, BillingEvent $event, ?string $previousPlan, array $payload): void
    {
        if (! PostHogService::isEnabled()) {
            return;
        }

        TrackBilling::dispatch((string) $account->id, $event, $payload, $previousPlan);
    }
}
