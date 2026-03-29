<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

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
    public function step1(): Response
    {
        return Inertia::render('onboarding/Step1', [
            'personas' => Persona::toSelectArray(),
        ]);
    }

    public function storeStep1(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'persona' => ['required', Rule::enum(Persona::class)],
        ]);

        $request->user()->update([
            'persona' => $validated['persona'],
            'setup' => Setup::Connections,
        ]);

        return redirect()->route('app.onboarding.step2');
    }

    public function step2(Request $request): Response
    {
        $user = $request->user();
        $workspace = $user->currentWorkspace;

        $platforms = collect();

        if ($workspace) {
            $connectedAccounts = $workspace->socialAccounts;

            $platforms = collect(SocialPlatform::enabled())->map(fn ($platform) => [
                'value' => $platform->value,
                'label' => $platform->label(),
                'color' => $platform->color(),
                'connected' => $connectedAccounts->firstWhere('platform', $platform) !== null,
                'account' => $connectedAccounts->firstWhere('platform', $platform),
            ])->values();
        }

        return Inertia::render('onboarding/Step2', [
            'platforms' => $platforms,
            'hasWorkspace' => $workspace !== null,
        ]);
    }

    public function storeStep2(Request $request): SymfonyResponse|RedirectResponse
    {
        $user = $request->user();

        if (config('trypost.self_hosted')) {
            $user->update([
                'setup' => Setup::Completed,
            ]);

            session()->flash('flash.banner', __('auth.flash.welcome'));
            session()->flash('flash.bannerStyle', 'success');

            return redirect()->route('app.calendar');
        }

        $user->update([
            'setup' => Setup::Subscription,
        ]);

        $subscription = $user->newSubscription('default', config('cashier.plans.monthly.price_id'))
            ->allowPromotionCodes()
            ->trialDays(config('cashier.trial_days'))
            ->quantity(1);

        $checkoutSession = $subscription->checkout([
            'success_url' => route('app.onboarding.complete').'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('app.onboarding.step2'),
        ]);

        return Inertia::location($checkoutSession->url);
    }

    public function complete(Request $request): RedirectResponse
    {
        $user = $request->user();

        $user->update([
            'setup' => Setup::Completed,
        ]);

        session()->flash('flash.banner', __('auth.flash.welcome_trial'));
        session()->flash('flash.bannerStyle', 'success');

        return redirect()->route('app.calendar');
    }
}
