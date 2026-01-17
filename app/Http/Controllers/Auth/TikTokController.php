<?php

namespace App\Http\Controllers\Auth;

use App\Enums\SocialPlatform;
use App\Enums\Status;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Response;

class TikTokController extends SocialController
{
    protected string $driver = 'tiktok';

    protected SocialPlatform $platform = SocialPlatform::TikTok;

    protected array $scopes = [
        'user.info.basic',
        'user.info.profile',
        'video.publish',
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

        session(['social_reconnect_id' => $existingAccount?->id]);

        return $this->redirectToProvider($request, $this->driver, $this->scopes);
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
            $socialUser = Socialite::driver($this->driver)
                ->scopes($this->scopes)
                ->user();

            Log::info('TikTok OAuth User Data', [
                'nickname' => $socialUser->getNickname(),
                'user' => $socialUser->user ?? [],
                'attributes' => $socialUser->attributes ?? [],
            ]);

            // TikTok returns username via getNickname() when user.info.profile scope is included
            $username = $socialUser->getNickname();
            $avatarPath = uploadFromUrl($socialUser->getAvatar());

            if ($existingAccount) {
                // Reconnect existing account
                $existingAccount->update([
                    'platform_user_id' => $socialUser->getId(),
                    'username' => $username,
                    'display_name' => $socialUser->getName(),
                    'avatar_url' => $avatarPath,
                    'access_token' => $socialUser->token,
                    'refresh_token' => $socialUser->refreshToken,
                    'token_expires_at' => $socialUser->expiresIn ? now()->addSeconds($socialUser->expiresIn) : null,
                    'scopes' => $socialUser->approvedScopes ?? null,
                ]);
                $existingAccount->markAsConnected();

                session()->forget('social_reconnect_id');

                return $this->popupCallback(true, 'TikTok account reconnected!', $this->platform->value);
            }

            // Create new account
            $workspace->socialAccounts()->create([
                'platform' => $this->platform->value,
                'platform_user_id' => $socialUser->getId(),
                'username' => $username,
                'display_name' => $socialUser->getName(),
                'avatar_url' => $avatarPath,
                'access_token' => $socialUser->token,
                'refresh_token' => $socialUser->refreshToken,
                'token_expires_at' => $socialUser->expiresIn ? now()->addSeconds($socialUser->expiresIn) : null,
                'scopes' => $socialUser->approvedScopes ?? null,
                'status' => Status::Connected,
            ]);

            session()->forget('social_reconnect_id');

            return $this->popupCallback(true, 'TikTok account connected!', $this->platform->value);
        } catch (\Exception $e) {
            Log::error('TikTok OAuth Error', [
                'error' => $e->getMessage(),
            ]);

            return $this->popupCallback(false, 'Error connecting account. Please try again.', $this->platform->value);
        }
    }
}
