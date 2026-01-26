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

class YouTubeController extends SocialController
{
    protected string $driver = 'google';

    protected SocialPlatform $platform = SocialPlatform::YouTube;

    protected array $scopes = [
        'https://www.googleapis.com/auth/youtube.upload',
        'https://www.googleapis.com/auth/youtube.readonly',
        'https://www.googleapis.com/auth/youtube.force-ssl',
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
            session()->flash('flash.banner', __('accounts.flash.already_connected'));
            session()->flash('flash.bannerStyle', 'danger');

            return back();
        }

        session([
            'social_connect_workspace' => $workspace->id,
            'social_reconnect_id' => $existingAccount?->id,
            'social_connect_onboarding' => $request->boolean('onboarding'),
        ]);

        return $this->redirectToGoogle();
    }

    public function callback(Request $request): View|RedirectResponse
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

            // Fetch the channels the user authorized
            $channels = $this->fetchChannels($socialUser->token);

            if (empty($channels)) {
                return $this->popupCallback(false, 'No YouTube channels found. Please create a channel first.', $this->platform->value);
            }

            // If only one channel, connect directly (most common case)
            if (count($channels) === 1) {
                $channel = $channels[0];
                $avatarPath = uploadFromUrl($channel['thumbnail']);

                if ($existingAccount) {
                    // Reconnect existing account
                    $existingAccount->update([
                        'platform_user_id' => $channel['id'],
                        'username' => ltrim($channel['custom_url'] ?? $channel['id'], '@'),
                        'display_name' => $channel['title'],
                        'avatar_url' => $avatarPath,
                        'access_token' => $socialUser->token,
                        'refresh_token' => $socialUser->refreshToken,
                        'token_expires_at' => $socialUser->expiresIn ? now()->addSeconds($socialUser->expiresIn) : null,
                        'scopes' => $this->scopes,
                        'meta' => [
                            'channel_id' => $channel['id'],
                            'google_user_id' => $socialUser->getId(),
                        ],
                    ]);
                    $existingAccount->markAsConnected();

                    session()->forget('social_reconnect_id');

                    return $this->popupCallback(true, 'YouTube channel reconnected!', $this->platform->value);
                }

                // Create new account
                $workspace->socialAccounts()->create([
                    'platform' => $this->platform->value,
                    'platform_user_id' => $channel['id'],
                    'username' => ltrim($channel['custom_url'] ?? $channel['id'], '@'),
                    'display_name' => $channel['title'],
                    'avatar_url' => $avatarPath,
                    'access_token' => $socialUser->token,
                    'refresh_token' => $socialUser->refreshToken,
                    'token_expires_at' => $socialUser->expiresIn ? now()->addSeconds($socialUser->expiresIn) : null,
                    'scopes' => $this->scopes,
                    'status' => Status::Connected,
                    'meta' => [
                        'channel_id' => $channel['id'],
                        'google_user_id' => $socialUser->getId(),
                    ],
                ]);

                session()->forget('social_reconnect_id');

                return $this->popupCallback(true, 'YouTube channel connected!', $this->platform->value);
            }

            // Multiple channels - store data and show selection screen
            session([
                'youtube_oauth' => [
                    'access_token' => $socialUser->token,
                    'refresh_token' => $socialUser->refreshToken,
                    'expires_in' => $socialUser->expiresIn,
                    'user_id' => $socialUser->getId(),
                    'reconnect_id' => $reconnectId,
                ],
            ]);

            return redirect()->route('social.youtube.select-channel');
        } catch (\Exception $e) {
            Log::error('YouTube OAuth Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->popupCallback(false, 'Error connecting account. Please try again.', $this->platform->value);
        }
    }

    public function selectChannel(Request $request)
    {
        $oauthData = session('youtube_oauth');
        $workspaceId = session('social_connect_workspace');

        if (! $oauthData || ! $workspaceId) {
            session()->flash('flash.banner', __('accounts.flash.session_expired'));
            session()->flash('flash.bannerStyle', 'danger');

            return redirect()->route('accounts');
        }

        $workspace = Workspace::find($workspaceId);

        if (! $workspace) {
            session()->flash('flash.banner', __('accounts.flash.workspace_not_found'));
            session()->flash('flash.bannerStyle', 'danger');

            return redirect()->route('accounts');
        }

        // Fetch YouTube channels
        $channels = $this->fetchChannels($oauthData['access_token']);

        if (empty($channels)) {
            $redirectRoute = $this->getRedirectRoute();
            $this->forgetSocialConnectSession();
            session()->forget('youtube_oauth');

            session()->flash('flash.banner', __('accounts.flash.no_youtube_channels'));
            session()->flash('flash.bannerStyle', 'danger');

            return redirect()->route($redirectRoute);
        }

        return inertia('accounts/YouTubeChannelSelect', [
            'workspace' => $workspace,
            'channels' => $channels,
        ]);
    }

    public function select(Request $request): View
    {
        $request->validate([
            'channel_id' => 'required|string',
        ]);

        $oauthData = session('youtube_oauth');
        $workspaceId = session('social_connect_workspace');

        if (! $oauthData || ! $workspaceId) {
            return $this->popupCallback(false, 'Session expired. Please try again.', $this->platform->value);
        }

        $workspace = Workspace::find($workspaceId);

        if (! $workspace || ! $request->user()->can('manageAccounts', $workspace)) {
            return $this->popupCallback(false, 'Workspace not found.', $this->platform->value);
        }

        try {
            $channels = $this->fetchChannels($oauthData['access_token']);
            $selectedChannel = collect($channels)->firstWhere('id', $request->channel_id);

            if (! $selectedChannel) {
                return $this->popupCallback(false, 'Channel not found.', $this->platform->value);
            }

            $avatarPath = uploadFromUrl($selectedChannel['thumbnail']);
            $reconnectId = $oauthData['reconnect_id'] ?? null;

            if ($reconnectId) {
                // Reconnect existing account
                $existingAccount = $workspace->socialAccounts()->find($reconnectId);

                if ($existingAccount) {
                    $existingAccount->update([
                        'platform_user_id' => $selectedChannel['id'],
                        'username' => ltrim($selectedChannel['custom_url'] ?? $selectedChannel['id'], '@'),
                        'display_name' => $selectedChannel['title'],
                        'avatar_url' => $avatarPath,
                        'access_token' => $oauthData['access_token'],
                        'refresh_token' => $oauthData['refresh_token'],
                        'token_expires_at' => $oauthData['expires_in'] ? now()->addSeconds($oauthData['expires_in']) : null,
                        'scopes' => $this->scopes,
                        'meta' => [
                            'channel_id' => $selectedChannel['id'],
                            'google_user_id' => $oauthData['user_id'],
                        ],
                    ]);
                    $existingAccount->markAsConnected();

                    session()->forget(['youtube_oauth', 'social_reconnect_id']);

                    return $this->popupCallback(true, 'YouTube channel reconnected!', $this->platform->value);
                }
            }

            // Create new account
            $workspace->socialAccounts()->create([
                'platform' => $this->platform->value,
                'platform_user_id' => $selectedChannel['id'],
                'username' => ltrim($selectedChannel['custom_url'] ?? $selectedChannel['id'], '@'),
                'display_name' => $selectedChannel['title'],
                'avatar_url' => $avatarPath,
                'access_token' => $oauthData['access_token'],
                'refresh_token' => $oauthData['refresh_token'],
                'token_expires_at' => $oauthData['expires_in'] ? now()->addSeconds($oauthData['expires_in']) : null,
                'scopes' => $this->scopes,
                'status' => Status::Connected,
                'meta' => [
                    'channel_id' => $selectedChannel['id'],
                    'google_user_id' => $oauthData['user_id'],
                ],
            ]);

            session()->forget(['youtube_oauth', 'social_reconnect_id']);

            return $this->popupCallback(true, 'YouTube channel connected!', $this->platform->value);
        } catch (\Exception $e) {
            Log::error('YouTube channel selection error', [
                'error' => $e->getMessage(),
            ]);

            return $this->popupCallback(false, 'Error connecting channel. Please try again.', $this->platform->value);
        }
    }

    private function redirectToGoogle(): Response
    {
        return \Inertia\Inertia::location(
            Socialite::driver($this->driver)
                ->scopes($this->scopes)
                ->with([
                    'access_type' => 'offline',
                    'prompt' => 'consent',
                    'include_granted_scopes' => 'true',
                ])
                ->redirect()
                ->getTargetUrl()
        );
    }

    private function fetchChannels(string $accessToken): array
    {
        try {
            $response = \Illuminate\Support\Facades\Http::withToken($accessToken)
                ->get('https://www.googleapis.com/youtube/v3/channels', [
                    'part' => 'snippet,contentDetails,statistics',
                    'mine' => 'true',
                ]);

            if ($response->failed()) {
                Log::error('YouTube channels fetch failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [];
            }

            $data = $response->json();

            return collect($data['items'] ?? [])->map(fn ($channel) => [
                'id' => $channel['id'],
                'title' => $channel['snippet']['title'],
                'description' => $channel['snippet']['description'] ?? '',
                'thumbnail' => $channel['snippet']['thumbnails']['default']['url'] ?? null,
                'custom_url' => $channel['snippet']['customUrl'] ?? null,
                'subscriber_count' => $channel['statistics']['subscriberCount'] ?? 0,
            ])->toArray();
        } catch (\Exception $e) {
            Log::error('YouTube channels fetch error', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
