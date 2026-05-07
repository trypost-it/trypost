<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\SocialAccount\Platform as SocialPlatform;
use App\Enums\SocialAccount\Status;
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
        'user.info.stats',
        'video.publish',
        'video.upload',
        'video.list',
    ];

    public function connect(Request $request): Response|RedirectResponse
    {
        $this->ensurePlatformEnabled();

        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('app.workspaces.create');
        }

        $this->authorize('manageAccounts', $workspace);

        session(['social_reconnect_id' => null]);

        return $this->redirectToProvider($request, $this->driver, $this->scopes);
    }

    public function callback(Request $request): View
    {
        $workspaceId = session('social_connect_workspace');

        if (! $workspaceId) {
            return $this->popupCallback(false, __('accounts.popup_callback.session_expired'), $this->platform->value);
        }

        $workspace = Workspace::find($workspaceId);

        if (! $workspace || ! $request->user()->can('manageAccounts', $workspace)) {
            return $this->popupCallback(false, __('accounts.popup_callback.workspace_not_found'), $this->platform->value);
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

            $workspace->socialAccounts()->updateOrCreate(
                [
                    'platform' => $this->platform->value,
                    'platform_user_id' => $socialUser->getId(),
                ],
                [
                    'username' => $username,
                    'display_name' => $socialUser->getName(),
                    'avatar_url' => $avatarPath,
                    'access_token' => $socialUser->token,
                    'refresh_token' => $socialUser->refreshToken,
                    'token_expires_at' => $socialUser->expiresIn ? now()->addSeconds($socialUser->expiresIn) : null,
                    'scopes' => $socialUser->approvedScopes ?? null,
                    'status' => Status::Connected,
                    'error_message' => null,
                    'disconnected_at' => null,
                ],
            );

            session()->forget('social_reconnect_id');

            return $this->popupCallback(true, __('accounts.popup_callback.connected'), $this->platform->value);
        } catch (\Exception $e) {
            Log::error('TikTok OAuth Error', [
                'error' => $e->getMessage(),
            ]);

            return $this->popupCallback(false, __('accounts.popup_callback.error_connecting'), $this->platform->value);
        }
    }
}
