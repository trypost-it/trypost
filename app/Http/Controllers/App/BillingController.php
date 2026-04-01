<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class BillingController extends Controller
{
    public function subscribe(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        $this->authorizeBilling($request);

        if ($user->subscribed(User::SUBSCRIPTION_NAME)) {
            return redirect()->route('app.billing.index');
        }

        return Inertia::render('billing/Subscribe', [
            'trialDays' => config('cashier.trial_days'),
        ]);
    }

    public function index(Request $request): Response|RedirectResponse
    {
        $this->authorizeBilling($request);

        $user = $request->user();
        $subscription = $user->subscription(User::SUBSCRIPTION_NAME);

        return Inertia::render('billing/Index', [
            'hasSubscription' => $user->subscribed(User::SUBSCRIPTION_NAME),
            'onTrial' => $subscription?->onTrial() ?? false,
            'trialEndsAt' => $subscription?->trial_ends_at?->toFormattedDateString(),
            'subscription' => $subscription?->only([
                'stripe_status',
                'quantity',
                'ends_at',
            ]),
            'workspacesCount' => $user->ownedWorkspacesCount(),
            'invoices' => $user->invoices()->map(fn ($invoice) => [
                'id' => $invoice->id,
                'date' => $invoice->date()->toFormattedDateString(),
                'total' => $invoice->total(),
                'status' => $invoice->status,
                'invoice_pdf' => $invoice->invoice_pdf,
            ]),
            'defaultPaymentMethod' => $user->defaultPaymentMethod()?->card?->only([
                'brand',
                'last4',
                'exp_month',
                'exp_year',
            ]),
        ]);
    }

    public function checkout(Request $request): SymfonyResponse
    {
        $this->authorizeBilling($request);

        $user = $request->user();

        $subscription = $user->newSubscription(User::SUBSCRIPTION_NAME, config('cashier.plans.monthly.price_id'))
            ->allowPromotionCodes()
            ->trialDays(config('cashier.trial_days'))
            ->quantity(1);

        $checkoutSession = $subscription->checkout([
            'success_url' => route('app.billing.processing').'?status=success',
            'cancel_url' => route('app.billing.processing').'?status=cancelled',
        ]);

        return Inertia::location($checkoutSession->url);
    }

    public function processing(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        $status = $request->query('status', 'processing');

        if ($user->subscribed(User::SUBSCRIPTION_NAME)) {
            return redirect()->route('app.calendar');
        }

        if (! in_array($status, ['processing', 'success', 'cancelled'])) {
            $status = 'processing';
        }

        return Inertia::render('billing/Processing', [
            'userId' => $user->id,
            'status' => $status,
        ]);
    }

    public function portal(Request $request): RedirectResponse
    {
        $this->authorizeBilling($request);

        return $request->user()->redirectToBillingPortal(
            route('app.billing.index')
        );
    }

    private function authorizeBilling(Request $request): void
    {
        $workspace = $request->user()->currentWorkspace;

        if ($workspace) {
            $this->authorize('manageBilling', $workspace);
        }
    }
}
