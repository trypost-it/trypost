<?php

namespace App\Http\Controllers\Auth;

use App\Enums\SocialPlatform;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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

    public function connect(Request $request, Workspace $workspace): Response
    {
        $this->ensurePlatformEnabled();
        $this->authorize('manageAccounts', $workspace);

        if ($workspace->hasConnectedPlatform($this->platform->value)) {
            return back()->with('error', 'This platform is already connected.');
        }

        return $this->redirectToProvider($workspace, $this->driver, $this->scopes);
    }

    public function callback(Request $request): RedirectResponse
    {
        $workspaceId = session('social_connect_workspace');

        if (! $workspaceId) {
            return redirect()->route('workspaces.index')
                ->with('error', 'Session expired. Please try again.');
        }

        $workspace = Workspace::find($workspaceId);

        if (! $workspace || ! $request->user()->can('manageAccounts', $workspace)) {
            return redirect()->route('workspaces.index')
                ->with('error', 'Workspace not found.');
        }

        if ($workspace->hasConnectedPlatform($this->platform->value)) {
            return redirect()->route('workspaces.accounts', $workspace)
                ->with('error', 'This platform is already connected.');
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
            ]);

            session()->forget('social_connect_workspace');

            return redirect()->route('workspaces.accounts', $workspace)
                ->with('success', 'Account connected successfully!');
        } catch (\Exception $e) {
            Log::error('TikTok OAuth Error', [
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('workspaces.accounts', $workspace)
                ->with('error', 'Error connecting account. Please try again.');
        }
    }
}
