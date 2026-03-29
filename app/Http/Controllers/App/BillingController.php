<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

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

        if ($user->subscribed('default')) {
            return redirect()->route('app.billing.index');
        }

        return Inertia::render('billing/Subscribe', [
            'trialDays' => config('cashier.trial_days'),
        ]);
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $subscription = $user->subscription('default');

        return Inertia::render('billing/Index', [
            'hasSubscription' => $user->subscribed('default'),
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
        $user = $request->user();

        $subscription = $user->newSubscription('default', config('cashier.plans.monthly.price_id'))
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

        if ($user->subscribed('default')) {
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
        return $request->user()->redirectToBillingPortal(
            route('app.billing.index')
        );
    }

    public function addWorkspace(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->subscribed('default')) {
            return redirect()->route('app.billing.index')
                ->withErrors(['subscription' => 'You need an active subscription.']);
        }

        $user->incrementWorkspaceQuantity();

        return back()->with('success', 'Workspace added to subscription.');
    }

    public function removeWorkspace(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->subscribed('default')) {
            return back();
        }

        $user->decrementWorkspaceQuantity();

        return back()->with('success', 'Workspace removed from subscription.');
    }
}
