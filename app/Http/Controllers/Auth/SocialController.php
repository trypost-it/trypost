<?php

namespace App\Http\Controllers\Auth;

use App\Enums\SocialPlatform;
use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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

    public function index(Workspace $workspace): Response
    {
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

    public function disconnect(Workspace $workspace, SocialAccount $account): RedirectResponse
    {
        $this->authorize('manageAccounts', $workspace);

        if ($account->workspace_id !== $workspace->id) {
            abort(403);
        }

        $account->delete();

        session()->flash('flash.banner', 'Account disconnected successfully!');
        session()->flash('flash.bannerStyle', 'success');

        return back();
    }

    protected function redirectToProvider(Workspace $workspace, string $driver, array $scopes): SymfonyResponse
    {
        session(['social_connect_workspace' => $workspace->id]);

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
    ): RedirectResponse {
        $workspaceId = session('social_connect_workspace');

        if (! $workspaceId) {
            session()->flash('flash.banner', 'Session expired. Please try again.');
            session()->flash('flash.bannerStyle', 'danger');

            return redirect()->route('workspaces.index');
        }

        $workspace = Workspace::find($workspaceId);

        if (! $workspace || ! $request->user()->can('manageAccounts', $workspace)) {
            session()->flash('flash.banner', 'Workspace not found.');
            session()->flash('flash.bannerStyle', 'danger');

            return redirect()->route('workspaces.index');
        }

        if ($workspace->hasConnectedPlatform($platform->value)) {
            session()->flash('flash.banner', 'This platform is already connected.');
            session()->flash('flash.bannerStyle', 'danger');

            return redirect()->route('workspaces.accounts', $workspace);
        }

        try {
            $socialUser = Socialite::driver($driver)->user();
            $avatarPath = uploadFromUrl($socialUser->getAvatar());

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
            ]);

            session()->forget('social_connect_workspace');

            session()->flash('flash.banner', 'Account connected successfully!');
            session()->flash('flash.bannerStyle', 'success');

            return redirect()->route('workspaces.accounts', $workspace);
        } catch (\Exception $e) {
            Log::error('Social OAuth Error', [
                'platform' => $platform->value,
                'error' => $e->getMessage(),
            ]);

            session()->flash('flash.banner', 'Error connecting account. Please try again.');
            session()->flash('flash.bannerStyle', 'danger');

            return redirect()->route('workspaces.accounts', $workspace);
        }
    }
}
