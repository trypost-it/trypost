<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BillingController extends Controller
{
    /**
     * Show the billing dashboard.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('billing/Index', [
            'hasSubscription' => $user->hasActiveSubscription(),
            'subscription' => $user->subscription('default')?->only([
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

    /**
     * Create a Stripe Checkout session for new subscription.
     */
    public function checkout(Request $request): RedirectResponse
    {
        $user = $request->user();

        // Calculate quantity based on workspaces (minimum 1)
        $quantity = max(1, $user->ownedWorkspacesCount());

        return $user->newSubscription('default', config('services.stripe.price_id'))
            ->quantity($quantity)
            ->checkout([
                'success_url' => route('billing.index') . '?checkout=success',
                'cancel_url' => route('billing.index') . '?checkout=cancelled',
            ])
            ->redirect();
    }

    /**
     * Redirect to Stripe Customer Portal.
     */
    public function portal(Request $request): RedirectResponse
    {
        return $request->user()->redirectToBillingPortal(
            route('billing.index')
        );
    }

    /**
     * Add a workspace to subscription (increment quantity).
     */
    public function addWorkspace(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->hasActiveSubscription()) {
            return redirect()->route('billing.index')
                ->withErrors(['subscription' => 'Você precisa de uma assinatura ativa.']);
        }

        $user->incrementWorkspaceQuantity();

        return back()->with('success', 'Workspace adicionado à assinatura.');
    }

    /**
     * Remove a workspace from subscription (decrement quantity).
     */
    public function removeWorkspace(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->hasActiveSubscription()) {
            return back();
        }

        $user->decrementWorkspaceQuantity();

        return back()->with('success', 'Workspace removido da assinatura.');
    }
}
