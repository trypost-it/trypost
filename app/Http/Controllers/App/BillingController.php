<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Models\Account;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class BillingController extends Controller
{
    public function subscribe(Request $request): Response|RedirectResponse
    {
        $account = $request->user()->account;

        if ($account && $account->hasActiveSubscription()) {
            return redirect()->route('app.billing.index');
        }

        return Inertia::render('billing/Subscribe', [
            'plans' => Plan::active()->orderBy('sort')->get(),
            'trialDays' => config('cashier.trial_days'),
        ]);
    }

    public function checkout(Request $request, Plan $plan): SymfonyResponse
    {
        $account = $request->user()->account;

        abort_unless($request->user()->isAccountOwner(), SymfonyResponse::HTTP_FORBIDDEN);

        $priceId = $request->input('interval', 'monthly') === 'yearly'
            ? $plan->stripe_yearly_price_id
            : $plan->stripe_monthly_price_id;

        abort_if(! $priceId, 422, 'Plan price not configured');

        $account->createOrGetStripeCustomer([
            'email' => $account->stripeEmail(),
            'name' => $account->stripeName(),
        ]);

        $subscription = $account->newSubscription(Account::SUBSCRIPTION_NAME, $priceId)
            ->allowPromotionCodes()
            ->trialDays(config('cashier.trial_days'));

        $checkoutSession = $subscription->checkout([
            'success_url' => route('app.billing.processing').'?status=success',
            'cancel_url' => route('app.billing.processing').'?status=cancelled',
        ]);

        $account->update(['plan_id' => $plan->id]);

        return Inertia::location($checkoutSession->url);
    }

    public function processing(Request $request): Response|RedirectResponse
    {
        $account = $request->user()->account;
        $status = $request->query('status', 'processing');

        if ($account && $account->subscribed(Account::SUBSCRIPTION_NAME)) {
            return redirect()->route('app.calendar');
        }

        if (! in_array($status, ['processing', 'success', 'cancelled'])) {
            $status = 'processing';
        }

        return Inertia::render('billing/Processing', [
            'accountId' => $account?->id,
            'status' => $status,
        ]);
    }

    public function index(Request $request): Response
    {
        $account = $request->user()->account;

        abort_unless($request->user()->isAccountOwner(), SymfonyResponse::HTTP_FORBIDDEN);

        $subscription = $account->subscription(Account::SUBSCRIPTION_NAME);

        return Inertia::render('billing/Index', [
            'hasSubscription' => $account->subscribed(Account::SUBSCRIPTION_NAME),
            'onTrial' => $subscription?->onTrial() ?? false,
            'trialEndsAt' => $subscription?->trial_ends_at?->toFormattedDateString(),
            'subscription' => $subscription?->only([
                'stripe_status',
                'ends_at',
            ]),
            'plan' => $account->plan,
            'plans' => Plan::active()->orderBy('sort')->get(),
            'invoices' => $account->invoices()->map(fn ($invoice) => [
                'id' => $invoice->id,
                'date' => $invoice->date()->toFormattedDateString(),
                'total' => $invoice->total(),
                'status' => $invoice->status,
                'invoice_pdf' => $invoice->invoice_pdf,
            ]),
            'defaultPaymentMethod' => $account->defaultPaymentMethod()?->card?->only([
                'brand',
                'last4',
                'exp_month',
                'exp_year',
            ]),
        ]);
    }

    public function swap(Request $request, Plan $plan): RedirectResponse
    {
        $account = $request->user()->account;

        abort_unless($request->user()->isAccountOwner(), SymfonyResponse::HTTP_FORBIDDEN);
        abort_unless($account->subscribed(Account::SUBSCRIPTION_NAME), 422, 'No active subscription');

        $priceId = $request->input('interval', 'monthly') === 'yearly'
            ? $plan->stripe_yearly_price_id
            : $plan->stripe_monthly_price_id;

        abort_if(! $priceId, 422, 'Plan price not configured');

        $account->subscription(Account::SUBSCRIPTION_NAME)->swap($priceId);
        $account->update(['plan_id' => $plan->id]);

        return redirect()->route('app.billing.index');
    }

    public function portal(Request $request): RedirectResponse
    {
        $account = $request->user()->account;

        abort_unless($request->user()->isAccountOwner(), SymfonyResponse::HTTP_FORBIDDEN);

        return $account->redirectToBillingPortal(
            route('app.billing.index')
        );
    }
}
