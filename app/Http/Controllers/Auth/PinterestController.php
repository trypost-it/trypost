<?php

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
            return redirect()->route('workspaces.create');
        }

        $this->authorize('manageAccounts', $workspace);

        $existingAccount = $workspace->socialAccounts()
            ->where('platform', $this->platform->value)
            ->first();

        if ($existingAccount && ! $existingAccount->isDisconnected()) {
            return back()->with('error', 'This platform is already connected.');
        }

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

        try {
            $socialUser = Socialite::driver($this->driver)->user();
            $existingAccount = $workspace->socialAccounts()
                ->where('platform', $this->platform->value)
                ->first();

            // If account exists and is connected, don't allow duplicate
            if ($existingAccount && ! $existingAccount->isDisconnected()) {
                return $this->popupCallback(false, 'This platform is already connected.', $this->platform->value);
            }

            Log::info('Pinterest OAuth User Data', [
                'id' => $socialUser->getId(),
                'nickname' => $socialUser->getNickname(),
                'name' => $socialUser->getName(),
                'user' => $socialUser->user ?? [],
            ]);

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
                    'token_expires_at' => $socialUser->expiresIn ? now()->addSeconds($socialUser->expiresIn) : now()->addDays(30),
                    'scopes' => $socialUser->approvedScopes ?? $this->scopes,
                ]);
                $existingAccount->markAsConnected();

                return $this->popupCallback(true, 'Pinterest account reconnected!', $this->platform->value);
            }

            // Create new account
            $workspace->socialAccounts()->create([
                'platform' => $this->platform->value,
                'platform_user_id' => $socialUser->getId(),
                'username' => $socialUser->getNickname(),
                'display_name' => $socialUser->getName(),
                'avatar_url' => $avatarPath,
                'access_token' => $socialUser->token,
                'refresh_token' => $socialUser->refreshToken,
                'token_expires_at' => $socialUser->expiresIn ? now()->addSeconds($socialUser->expiresIn) : now()->addDays(30),
                'scopes' => $socialUser->approvedScopes ?? $this->scopes,
                'status' => Status::Connected,
            ]);

            return $this->popupCallback(true, 'Pinterest account connected!', $this->platform->value);
        } catch (\Exception $e) {
            Log::error('Pinterest OAuth Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->popupCallback(false, 'Error connecting account. Please try again.', $this->platform->value);
        }
    }
}
