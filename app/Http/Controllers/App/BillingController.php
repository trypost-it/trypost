<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Models\Plan;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class BillingController extends Controller
{
    public function subscribe(Request $request): Response|RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if ($workspace && $workspace->hasActiveSubscription()) {
            return redirect()->route('app.billing.index');
        }

        return Inertia::render('billing/Subscribe', [
            'plans' => Plan::active()->orderBy('sort')->get(),
            'trialDays' => config('cashier.trial_days'),
        ]);
    }

    public function checkout(Request $request, Plan $plan): SymfonyResponse
    {
        $workspace = $request->user()->currentWorkspace;

        $this->authorize('manageBilling', $workspace);

        $priceId = $request->input('interval', 'monthly') === 'yearly'
            ? $plan->stripe_yearly_price_id
            : $plan->stripe_monthly_price_id;

        abort_if(! $priceId, 422, 'Plan price not configured');

        $workspace->createOrGetStripeCustomer([
            'email' => $workspace->stripeEmail(),
            'name' => $workspace->stripeName(),
        ]);

        $subscription = $workspace->newSubscription(Workspace::SUBSCRIPTION_NAME, $priceId)
            ->allowPromotionCodes()
            ->trialDays(config('cashier.trial_days'));

        $checkoutSession = $subscription->checkout([
            'success_url' => route('app.billing.processing').'?status=success',
            'cancel_url' => route('app.billing.processing').'?status=cancelled',
        ]);

        $workspace->update(['plan_id' => $plan->id]);

        return Inertia::location($checkoutSession->url);
    }

    public function processing(Request $request): Response|RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;
        $status = $request->query('status', 'processing');

        if ($workspace && $workspace->subscribed(Workspace::SUBSCRIPTION_NAME)) {
            return redirect()->route('app.calendar');
        }

        if (! in_array($status, ['processing', 'success', 'cancelled'])) {
            $status = 'processing';
        }

        return Inertia::render('billing/Processing', [
            'workspaceId' => $workspace?->id,
            'status' => $status,
        ]);
    }

    public function index(Request $request): Response
    {
        $workspace = $request->user()->currentWorkspace;

        $this->authorize('manageBilling', $workspace);

        $subscription = $workspace->subscription(Workspace::SUBSCRIPTION_NAME);

        return Inertia::render('billing/Index', [
            'hasSubscription' => $workspace->subscribed(Workspace::SUBSCRIPTION_NAME),
            'onTrial' => $subscription?->onTrial() ?? false,
            'trialEndsAt' => $subscription?->trial_ends_at?->toFormattedDateString(),
            'subscription' => $subscription?->only([
                'stripe_status',
                'ends_at',
            ]),
            'plan' => $workspace->plan,
            'plans' => Plan::active()->orderBy('sort')->get(),
            'invoices' => $workspace->invoices()->map(fn ($invoice) => [
                'id' => $invoice->id,
                'date' => $invoice->date()->toFormattedDateString(),
                'total' => $invoice->total(),
                'status' => $invoice->status,
                'invoice_pdf' => $invoice->invoice_pdf,
            ]),
            'defaultPaymentMethod' => $workspace->defaultPaymentMethod()?->card?->only([
                'brand',
                'last4',
                'exp_month',
                'exp_year',
            ]),
        ]);
    }

    public function swap(Request $request, Plan $plan): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        $this->authorize('manageBilling', $workspace);

        abort_unless($workspace->subscribed(Workspace::SUBSCRIPTION_NAME), 422, 'No active subscription');

        $priceId = $request->input('interval', 'monthly') === 'yearly'
            ? $plan->stripe_yearly_price_id
            : $plan->stripe_monthly_price_id;

        abort_if(! $priceId, 422, 'Plan price not configured');

        $workspace->subscription(Workspace::SUBSCRIPTION_NAME)->swap($priceId);
        $workspace->update(['plan_id' => $plan->id]);

        return redirect()->route('app.billing.index');
    }

    public function portal(Request $request): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        $this->authorize('manageBilling', $workspace);

        return $workspace->redirectToBillingPortal(
            route('app.billing.index')
        );
    }
}
