<?php

namespace App\Http\Controllers;

use App\Enums\SocialAccount\Platform as SocialPlatform;
use App\Enums\User\Persona;
use App\Enums\User\Setup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class OnboardingController extends Controller
{
    /**
     * Step 1: Select persona (user type).
     */
    public function step1(): Response
    {
        return Inertia::render('onboarding/Step1', [
            'personas' => Persona::toSelectArray(),
        ]);
    }

    /**
     * Store step 1 and proceed to step 2.
     */
    public function storeStep1(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'persona' => ['required', Rule::enum(Persona::class)],
        ]);

        $request->user()->update([
            'persona' => $validated['persona'],
            'setup' => Setup::Connections,
        ]);

        return redirect()->route('onboarding.step2');
    }

    /**
     * Step 2: Connect social accounts.
     */
    public function step2(Request $request): Response
    {
        $user = $request->user();
        $workspace = $user->currentWorkspace;

        $platforms = collect();

        if ($workspace) {
            $connectedAccounts = $workspace->socialAccounts;

            $platforms = collect(SocialPlatform::enabled())->map(function ($platform) use ($connectedAccounts) {
                $connected = $connectedAccounts->firstWhere('platform', $platform);

                return [
                    'value' => $platform->value,
                    'label' => $platform->label(),
                    'color' => $platform->color(),
                    'connected' => $connected !== null,
                    'account' => $connected,
                ];
            })->values();
        }

        return Inertia::render('onboarding/Step2', [
            'platforms' => $platforms,
            'hasWorkspace' => $workspace !== null,
        ]);
    }

    /**
     * Store step 2 and redirect to Stripe checkout (or complete if self-hosted).
     */
    public function storeStep2(Request $request): SymfonyResponse|RedirectResponse
    {
        $user = $request->user();

        // Skip payment for self-hosted mode
        if (config('trypost.self_hosted')) {
            $user->update([
                'setup' => Setup::Completed,
            ]);

            session()->flash('flash.banner', 'Welcome to TryPost!');
            session()->flash('flash.bannerStyle', 'success');

            return redirect()->route('calendar');
        }

        $user->update([
            'setup' => Setup::Subscription,
        ]);

        // Redirect to Stripe checkout
        $subscription = $user->newSubscription('default', config('cashier.plans.monthly.price_id'))
            ->allowPromotionCodes()
            ->trialDays(config('cashier.trial_days'))
            ->quantity(1);

        $checkoutSession = $subscription->checkout([
            'success_url' => route('onboarding.complete').'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('onboarding.step2'),
        ]);

        return Inertia::location($checkoutSession->url);
    }

    /**
     * Complete onboarding after successful Stripe checkout.
     */
    public function complete(Request $request): RedirectResponse
    {
        $user = $request->user();

        $user->update([
            'setup' => Setup::Completed,
        ]);

        session()->flash('flash.banner', 'Welcome to TryPost! Your trial has started.');
        session()->flash('flash.bannerStyle', 'success');

        return redirect()->route('calendar');
    }
}
