<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Enums\Plan\Slug as PlanSlug;
use App\Enums\SocialAccount\Platform as SocialPlatform;
use App\Enums\User\Persona;
use App\Enums\User\Setup;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class OnboardingController extends Controller
{
    public function role(Request $request): Response|RedirectResponse
    {
        $redirect = $this->enforceStep($request->user(), Setup::Role);
        if ($redirect) {
            return $redirect;
        }

        return Inertia::render('onboarding/Role', [
            'personas' => Persona::toSelectArray(),
        ]);
    }

    public function storeRole(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'persona' => ['required', Rule::enum(Persona::class)],
        ]);

        $request->user()->update([
            'persona' => data_get($validated, 'persona'),
            'setup' => Setup::Connections,
        ]);

        return redirect()->route('app.onboarding.connect');
    }

    public function connect(Request $request): Response|RedirectResponse
    {
        $redirect = $this->enforceStep($request->user(), Setup::Connections);
        if ($redirect) {
            return $redirect;
        }

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

        return Inertia::render('onboarding/Connect', [
            'platforms' => $platforms,
            'hasWorkspace' => $workspace !== null,
        ]);
    }

    public function storeConnect(Request $request): SymfonyResponse|RedirectResponse
    {
        $user = $request->user();
        $workspace = $user->currentWorkspace;

        if (config('trypost.self_hosted')) {
            $user->update(['setup' => Setup::Completed]);

            session()->flash('flash.banner', __('auth.flash.welcome'));
            session()->flash('flash.bannerStyle', 'success');

            return redirect()->route('app.calendar');
        }

        $user->update(['setup' => Setup::Subscription]);

        $defaultPlan = Plan::where('slug', PlanSlug::Starter)->firstOrFail();

        $workspace->createOrGetStripeCustomer([
            'email' => $workspace->stripeEmail(),
            'name' => $workspace->stripeName(),
        ]);

        $subscription = $workspace->newSubscription(Workspace::SUBSCRIPTION_NAME, $defaultPlan->stripe_monthly_price_id)
            ->allowPromotionCodes()
            ->trialDays(config('cashier.trial_days'));

        $checkoutSession = $subscription->checkout([
            'success_url' => route('app.onboarding.complete').'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('app.onboarding.connect'),
        ]);

        $workspace->update(['plan_id' => $defaultPlan->id]);

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

    private function enforceStep(User $user, Setup $expectedStep): ?RedirectResponse
    {
        if ($user->setup === $expectedStep) {
            return null;
        }

        if ($user->setup === Setup::Completed) {
            return redirect()->route('app.calendar');
        }

        return match ($user->setup) {
            Setup::Role => redirect()->route('app.onboarding.role'),
            Setup::Connections => redirect()->route('app.onboarding.connect'),
            Setup::Subscription => redirect()->route('app.subscribe'),
            default => redirect()->route('app.onboarding.role'),
        };
    }
}
