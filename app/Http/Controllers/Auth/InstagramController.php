<?php

namespace App\Http\Controllers\Auth;

use App\Enums\SocialPlatform;
use App\Enums\Status;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Inertia\Inertia;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Response;

class InstagramController extends SocialController
{
    protected string $driver = 'instagram';

    protected SocialPlatform $platform = SocialPlatform::Instagram;

    protected array $scopes = [
        'instagram_business_basic',
        'instagram_business_content_publish',
    ];

    public function connect(Request $request): Response|RedirectResponse
    {
        $this->ensurePlatformEnabled();

        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('workspaces.create');
        }

        $this->authorize('manageAccounts', $workspace);

        $existingAccount = $workspace->socialAccounts()
            ->where('platform', $this->platform->value)
            ->first();

        if ($existingAccount && ! $existingAccount->isDisconnected()) {
            return back()->with('error', 'This platform is already connected.');
        }

        session([
            'social_connect_workspace' => $workspace->id,
            'social_reconnect_id' => $existingAccount?->id,
            'social_connect_onboarding' => $request->boolean('onboarding'),
        ]);

        $url = Socialite::driver($this->driver)
            ->scopes($this->scopes)
            ->redirect()
            ->getTargetUrl();

        return Inertia::location($url);
    }

    public function callback(Request $request): View
    {
        $workspaceId = session('social_connect_workspace');

        if (! $workspaceId) {
            return $this->popupCallback(false, 'Session expired. Please try again.', $this->platform->value);
        }

        $workspace = Workspace::find($workspaceId);

        if (! $workspace || ! $request->user()->can('manageAccounts', $workspace)) {
            return $this->popupCallback(false, 'Workspace not found.', $this->platform->value);
        }

        $reconnectId = session('social_reconnect_id');
        $existingAccount = $reconnectId ? $workspace->socialAccounts()->find($reconnectId) : null;

        // If account exists and is connected, don't allow duplicate
        if (! $existingAccount && $workspace->hasConnectedPlatform($this->platform->value)) {
            return $this->popupCallback(false, 'This platform is already connected.', $this->platform->value);
        }

        try {
            $socialUser = Socialite::driver($this->driver)->user();

            // Instagram API with Instagram Login returns the user directly
            $avatarPath = $socialUser->getAvatar() ? uploadFromUrl($socialUser->getAvatar()) : null;

            // Calculate token expiration (long-lived tokens last 60 days)
            $expiresIn = $socialUser->expiresIn ?? 5184000; // 60 days in seconds
            $tokenExpiresAt = now()->addSeconds($expiresIn);

            if ($existingAccount) {
                // Reconnect existing account
                $existingAccount->update([
                    'platform_user_id' => $socialUser->getId(),
                    'username' => $socialUser->getNickname(),
                    'display_name' => $socialUser->getName() ?? $socialUser->getNickname(),
                    'avatar_url' => $avatarPath,
                    'access_token' => $socialUser->token,
                    'refresh_token' => $socialUser->refreshToken,
                    'token_expires_at' => $tokenExpiresAt,
                    'scopes' => $this->scopes,
                    'meta' => [
                        'account_type' => $socialUser->user['account_type'] ?? null,
                    ],
                ]);
                $existingAccount->markAsConnected();

                session()->forget('social_reconnect_id');

                return $this->popupCallback(true, 'Instagram account reconnected!', $this->platform->value);
            }

            // Create new account
            $workspace->socialAccounts()->create([
                'platform' => $this->platform->value,
                'platform_user_id' => $socialUser->getId(),
                'username' => $socialUser->getNickname(),
                'display_name' => $socialUser->getName() ?? $socialUser->getNickname(),
                'avatar_url' => $avatarPath,
                'access_token' => $socialUser->token,
                'refresh_token' => $socialUser->refreshToken,
                'token_expires_at' => $tokenExpiresAt,
                'scopes' => $this->scopes,
                'status' => Status::Connected,
                'meta' => [
                    'account_type' => $socialUser->user['account_type'] ?? null,
                ],
            ]);

            session()->forget('social_reconnect_id');

            return $this->popupCallback(true, 'Instagram account connected!', $this->platform->value);
        } catch (\Exception $e) {
            Log::error('Instagram OAuth Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->popupCallback(false, 'Error connecting account. Please try again.', $this->platform->value);
        }
    }
}
