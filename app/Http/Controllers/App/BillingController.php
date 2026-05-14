<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Models\Account;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

class BillingController extends Controller
{
    public function subscribe(Request $request): Response|RedirectResponse
    {
        if (config('trypost.self_hosted')) {
            return redirect()->route('app.calendar');
        }

        $account = $request->user()->account;

        if ($account && $account->hasActiveSubscription()) {
            return redirect()->route('app.billing.index');
        }

        return Inertia::render('billing/Subscribe', [
            'plans' => Plan::active()->orderBy('sort')->get(),
            'trialDays' => config('cashier.trial_days'),
        ]);
    }

    public function checkout(Request $request, Plan $plan): SymfonyResponse|RedirectResponse
    {
        if (config('trypost.self_hosted')) {
            return redirect()->route('app.calendar');
        }

        $user = $request->user();
        $account = $user->account;

        abort_unless($user->isAccountOwner(), SymfonyResponse::HTTP_FORBIDDEN);

        $request->validate([
            'price_id' => ['required', 'string'],
        ]);

        $priceId = $request->input('price_id');

        abort_unless(
            $priceId === $plan->stripe_monthly_price_id || $priceId === $plan->stripe_yearly_price_id,
            422,
            'Invalid price for this plan',
        );

        $account->createOrGetStripeCustomer([
            'email' => $account->stripeEmail(),
            'name' => $account->stripeName(),
        ]);

        $subscription = $account->newSubscription(Account::SUBSCRIPTION_NAME, $priceId)
            ->allowPromotionCodes()
            ->trialDays(config('cashier.trial_days'));

        $checkoutSession = $subscription->checkout([
            'success_url' => route('app.billing.processing').'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('app.subscribe'),
        ]);

        return Inertia::location($checkoutSession->url);
    }

    public function processing(Request $request): Response|RedirectResponse
    {
        if (config('trypost.self_hosted')) {
            return redirect()->route('app.calendar');
        }

        $account = $request->user()->account;
        $sessionId = $request->query('session_id');

        return Inertia::render('billing/Processing', [
            'subscriptionActive' => $account && $account->subscribed(Account::SUBSCRIPTION_NAME),
            'conversion' => is_string($sessionId) && $sessionId !== '' && $account?->stripe_id
                ? fn () => $this->buildConversionData($account, $sessionId)
                : null,
        ]);
    }

    /**
     * @return array{value: float, currency: string, transaction_id: string}|null
     */
    private function buildConversionData(Account $account, string $sessionId): ?array
    {
        try {
            $session = $account->stripe()->checkout->sessions->retrieve(
                $sessionId,
                ['expand' => ['line_items.data.price']],
            );
        } catch (Throwable) {
            return null;
        }

        if (data_get($session, 'customer') !== $account->stripe_id) {
            return null;
        }

        $unitAmount = data_get($session, 'line_items.data.0.price.unit_amount');
        $currency = data_get($session, 'line_items.data.0.price.currency');
        $transactionId = data_get($session, 'id');

        if (! is_int($unitAmount) || ! is_string($currency) || ! is_string($transactionId)) {
            return null;
        }

        return [
            'value' => $unitAmount / 100,
            'currency' => strtoupper($currency),
            'transaction_id' => $transactionId,
        ];
    }

    public function index(Request $request): Response|RedirectResponse
    {
        if (config('trypost.self_hosted')) {
            return redirect()->route('app.calendar');
        }

        $account = $request->user()->account;

        abort_unless($request->user()->isAccountOwner(), SymfonyResponse::HTTP_FORBIDDEN);

        $subscription = $account->subscription(Account::SUBSCRIPTION_NAME);

        return Inertia::render('settings/account/Billing', [
            'hasSubscription' => $account->subscribed(Account::SUBSCRIPTION_NAME),
            'onTrial' => $account->isOnTrial(),
            'trialEndsAt' => $account->activeTrialEndsAt(),
            'subscription' => $subscription?->only([
                'stripe_status',
                'ends_at',
            ]),
            'plan' => $account->plan,
            'plans' => Plan::active()->orderBy('sort')->get(),
            'invoices' => $account->invoices()->map(fn ($invoice) => [
                'id' => $invoice->id,
                'date' => $invoice->date(),
                'total' => $invoice->total(),
                'status' => $invoice->status,
                'invoice_pdf' => $invoice->invoice_pdf,
            ]),
            'defaultPaymentMethod' => $account->displayablePaymentMethod(),
        ]);
    }

    public function swap(Request $request, Plan $plan): RedirectResponse
    {
        if (config('trypost.self_hosted')) {
            return redirect()->route('app.calendar');
        }

        $account = $request->user()->account;

        abort_unless($request->user()->isAccountOwner(), SymfonyResponse::HTTP_FORBIDDEN);
        abort_unless($account->subscribed(Account::SUBSCRIPTION_NAME), 422, 'No active subscription');

        $request->validate([
            'price_id' => ['required', 'string'],
        ]);

        $priceId = $request->input('price_id');
        $subscription = $account->subscription(Account::SUBSCRIPTION_NAME);

        abort_unless(
            $priceId === $plan->stripe_monthly_price_id || $priceId === $plan->stripe_yearly_price_id,
            422,
            'Invalid price for this plan',
        );

        $authorization = Gate::inspect('swapPlan', [$account, $plan]);

        if ($authorization->denied()) {
            return back()->with('flash.error', $authorization->message());
        }

        $subscription->swap($priceId);
        $account->update(['plan_id' => $plan->id]);
        $account->forgetPlanFeatureCache();

        return redirect()->route('app.billing.index')
            ->with('flash.success', __('billing.flash.plan_changed', ['plan' => $plan->name]));
    }

    public function portal(Request $request): RedirectResponse
    {
        if (config('trypost.self_hosted')) {
            return redirect()->route('app.calendar');
        }

        $account = $request->user()->account;

        abort_unless($request->user()->isAccountOwner(), SymfonyResponse::HTTP_FORBIDDEN);

        return $account->redirectToBillingPortal(
            route('app.billing.index')
        );
    }
}
