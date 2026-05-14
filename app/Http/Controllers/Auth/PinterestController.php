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

class PinterestController extends SocialController
{
    protected string $driver = 'pinterest';

    protected SocialPlatform $platform = SocialPlatform::Pinterest;

    protected array $scopes = [
        'boards:read',
        'boards:write',
        'pins:read',
        'pins:write',
        'user_accounts:read',
    ];

    public function connect(Request $request): Response|RedirectResponse
    {
        $this->ensurePlatformEnabled();

        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('app.workspaces.create');
        }

        $this->authorize('manageAccounts', $workspace);

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
            $socialUser = Socialite::driver($this->driver)->user();

            Log::info('Pinterest OAuth User Data', [
                'id' => $socialUser->getId(),
                'nickname' => $socialUser->getNickname(),
                'name' => $socialUser->getName(),
                'user' => $socialUser->user ?? [],
            ]);

            $avatarPath = uploadFromUrl($socialUser->getAvatar());

            // Create new account
            $workspace->socialAccounts()->create([
                'platform' => $this->platform->value,
                'platform_user_id' => $socialUser->getId(),
                'username' => $socialUser->getNickname(),
                'display_name' => $socialUser->getName() ?? $socialUser->getNickname(),
                'avatar_url' => $avatarPath,
                'access_token' => $socialUser->token,
                'refresh_token' => $socialUser->refreshToken,
                'token_expires_at' => $socialUser->expiresIn ? now()->addSeconds($socialUser->expiresIn) : now()->addDays(30),
                // Pinterest returns scopes space-joined but Socialite doesn't split them, so re-split here.
                'scopes' => explode(' ', implode(' ', $socialUser->approvedScopes)),
                'status' => Status::Connected,
            ]);

            return $this->popupCallback(true, __('accounts.popup_callback.connected'), $this->platform->value);
        } catch (\Exception $e) {
            Log::error('Pinterest OAuth Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->popupCallback(false, __('accounts.popup_callback.error_connecting'), $this->platform->value);
        }
    }
}
