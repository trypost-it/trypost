<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Enums\SocialAccount\Platform as SocialPlatform;
use App\Enums\User\Persona;
use App\Enums\User\Setup;
use App\Http\Requests\App\Onboarding\StoreBrandRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

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
            'setup' => Setup::Brand,
        ]);

        return redirect()->route('app.onboarding.brand');
    }

    public function brand(Request $request): Response|RedirectResponse
    {
        $redirect = $this->enforceStep($request->user(), Setup::Brand);
        if ($redirect) {
            return $redirect;
        }

        $workspace = $request->user()->currentWorkspace;

        return Inertia::render('onboarding/Brand', [
            'workspace' => [
                'name' => $workspace?->name ?? '',
                'brand_website' => $workspace?->brand_website ?? '',
                'brand_description' => $workspace?->brand_description ?? '',
                'brand_tone' => $workspace?->brand_tone ?? 'professional',
                'brand_voice_notes' => $workspace?->brand_voice_notes ?? '',
                'content_language' => $workspace?->content_language ?? 'en',
            ],
        ]);
    }

    public function storeBrand(StoreBrandRequest $request): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if ($workspace) {
            $workspace->update($request->validated());
        }

        $request->user()->update(['setup' => Setup::Connections]);

        return redirect()->route('app.onboarding.account');
    }

    public function skipBrand(Request $request): RedirectResponse
    {
        $request->user()->update(['setup' => Setup::Connections]);

        return redirect()->route('app.onboarding.account');
    }

    public function account(Request $request): Response|RedirectResponse
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

        return Inertia::render('onboarding/Account', [
            'platforms' => $platforms,
            'hasWorkspace' => $workspace !== null,
        ]);
    }

    public function storeAccount(Request $request): RedirectResponse
    {
        $request->user()->update(['setup' => Setup::Completed]);

        if (config('trypost.self_hosted')) {
            session()->flash('flash.banner', __('auth.flash.welcome'));
            session()->flash('flash.bannerStyle', 'success');

            return redirect()->route('app.calendar');
        }

        return redirect()->route('app.subscribe');
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
            Setup::Brand => redirect()->route('app.onboarding.brand'),
            Setup::Connections => redirect()->route('app.onboarding.account'),
            default => redirect()->route('app.onboarding.role'),
        };
    }
}
