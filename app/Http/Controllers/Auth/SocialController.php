<?php

namespace App\Http\Controllers\Auth;

use App\Enums\SocialAccount\Platform as SocialPlatform;
use App\Enums\SocialAccount\Status;
use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class SocialController extends Controller
{
    protected SocialPlatform $platform;

    protected function ensurePlatformEnabled(): void
    {
        if (isset($this->platform) && ! $this->platform->isEnabled()) {
            abort(403, 'This platform is currently unavailable.');
        }
    }

    public function index(Request $request): Response|RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('workspaces.create');
        }

        $this->authorize('view', $workspace);

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

        return Inertia::render('accounts/Index', [
            'workspace' => $workspace,
            'platforms' => $platforms,
        ]);
    }

    public function disconnect(Request $request, SocialAccount $account): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('workspaces.create');
        }

        $this->authorize('manageAccounts', $workspace);

        if ($account->workspace_id !== $workspace->id) {
            abort(403);
        }

        $account->delete();

        session()->flash('flash.banner', __('accounts.flash.disconnected'));
        session()->flash('flash.bannerStyle', 'success');

        return back();
    }

    protected function redirectToProvider(Request $request, string $driver, array $scopes): SymfonyResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('workspaces.create');
        }

        session(['social_connect_workspace' => $workspace->id]);
        session(['social_connect_onboarding' => $request->boolean('onboarding')]);

        return Inertia::location(
            Socialite::driver($driver)
                ->scopes($scopes)
                ->redirect()
                ->getTargetUrl()
        );
    }

    protected function handleCallback(
        Request $request,
        SocialPlatform $platform,
        string $driver
    ): View {
        $workspaceId = session('social_connect_workspace');

        if (! $workspaceId) {
            return $this->popupCallback(false, 'Session expired. Please try again.', $platform->value);
        }

        $workspace = Workspace::find($workspaceId);

        if (! $workspace || ! $request->user()->can('manageAccounts', $workspace)) {
            return $this->popupCallback(false, 'Workspace not found.', $platform->value);
        }

        try {
            $socialUser = Socialite::driver($driver)->user();
            $existingAccount = $workspace->socialAccounts()
                ->where('platform', $platform->value)
                ->first();

            // If account exists and is connected, don't allow duplicate
            if ($existingAccount && ! $existingAccount->isDisconnected()) {
                return $this->popupCallback(false, 'This platform is already connected.', $platform->value);
            }

            $avatarPath = uploadFromUrl($socialUser->getAvatar());

            if ($existingAccount) {
                // Reconnect existing account
                $existingAccount->update([
                    'platform_user_id' => $socialUser->getId(),
                    'username' => $socialUser->getNickname(),
                    'display_name' => $socialUser->getName(),
                    'avatar_url' => $avatarPath,
                    'access_token' => $socialUser->token,
                    'refresh_token' => $socialUser->refreshToken,
                    'token_expires_at' => $socialUser->expiresIn ? now()->addSeconds($socialUser->expiresIn) : null,
                    'scopes' => $socialUser->approvedScopes ?? null,
                ]);
                $existingAccount->markAsConnected();

                return $this->popupCallback(true, 'Account reconnected!', $platform->value);
            }

            // Create new account
            $workspace->socialAccounts()->create([
                'platform' => $platform->value,
                'platform_user_id' => $socialUser->getId(),
                'username' => $socialUser->getNickname(),
                'display_name' => $socialUser->getName(),
                'avatar_url' => $avatarPath,
                'access_token' => $socialUser->token,
                'refresh_token' => $socialUser->refreshToken,
                'token_expires_at' => $socialUser->expiresIn ? now()->addSeconds($socialUser->expiresIn) : null,
                'scopes' => $socialUser->approvedScopes ?? null,
                'status' => Status::Connected,
            ]);

            return $this->popupCallback(true, 'Account connected!', $platform->value);
        } catch (\Exception $e) {
            Log::error('Social OAuth Error', [
                'platform' => $platform->value,
                'error' => $e->getMessage(),
            ]);

            return $this->popupCallback(false, 'Error connecting account. Please try again.', $platform->value);
        }
    }

    protected function forgetSocialConnectSession(): void
    {
        session()->forget(['social_connect_workspace', 'social_connect_onboarding']);
    }

    protected function getRedirectRoute(): string
    {
        return session('social_connect_onboarding', false) ? 'onboarding.step2' : 'accounts';
    }

    /**
     * Return a view that closes the popup and notifies the parent window.
     */
    protected function popupCallback(bool $success, string $message, ?string $platform = null): View
    {
        $this->forgetSocialConnectSession();

        return view('auth.social-callback', [
            'success' => $success,
            'message' => $message,
            'platform' => $platform,
        ]);
    }
}
