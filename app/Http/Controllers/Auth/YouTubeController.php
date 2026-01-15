<?php

namespace App\Http\Controllers\Auth;

use App\Enums\SocialPlatform;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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

    public function connect(Request $request, Workspace $workspace): Response
    {
        $this->authorize('manageAccounts', $workspace);

        if ($workspace->hasConnectedPlatform($this->platform->value)) {
            return back()->with('error', 'This platform is already connected.');
        }

        session(['social_connect_workspace' => $workspace->id]);

        return $this->redirectToGoogle($workspace);
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
            $socialUser = Socialite::driver($this->driver)->user();

            // Fetch the channels the user authorized
            $channels = $this->fetchChannels($socialUser->token);

            if (empty($channels)) {
                return redirect()->route('workspaces.accounts', $workspace)
                    ->with('error', 'No YouTube channels found. Please create a channel first.');
            }

            // If only one channel, connect directly (most common case)
            if (count($channels) === 1) {
                $channel = $channels[0];
                $avatarPath = uploadFromUrl($channel['thumbnail']);

                $workspace->socialAccounts()->create([
                    'platform' => $this->platform->value,
                    'platform_user_id' => $channel['id'],
                    'username' => $channel['custom_url'] ?? $channel['id'],
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

                session()->forget('social_connect_workspace');

                return redirect()->route('workspaces.accounts', $workspace)
                    ->with('success', 'YouTube channel connected successfully!');
            }

            // Multiple channels - store data and show selection screen
            session([
                'youtube_oauth' => [
                    'access_token' => $socialUser->token,
                    'refresh_token' => $socialUser->refreshToken,
                    'expires_in' => $socialUser->expiresIn,
                    'user_id' => $socialUser->getId(),
                ],
            ]);

            return redirect()->route('social.youtube.select-channel');
        } catch (\Exception $e) {
            Log::error('YouTube OAuth Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('workspaces.accounts', $workspace)
                ->with('error', 'Error connecting account. Please try again.');
        }
    }

    public function selectChannel(Request $request)
    {
        $oauthData = session('youtube_oauth');
        $workspaceId = session('social_connect_workspace');

        if (! $oauthData || ! $workspaceId) {
            return redirect()->route('workspaces.index')
                ->with('error', 'Session expired. Please try again.');
        }

        $workspace = Workspace::find($workspaceId);

        if (! $workspace) {
            return redirect()->route('workspaces.index')
                ->with('error', 'Workspace not found.');
        }

        // Fetch YouTube channels
        $channels = $this->fetchChannels($oauthData['access_token']);

        if (empty($channels)) {
            session()->forget(['youtube_oauth', 'social_connect_workspace']);

            return redirect()->route('workspaces.accounts', $workspace)
                ->with('error', 'No YouTube channels found. Please create a channel first.');
        }

        return inertia('accounts/YouTubeChannelSelect', [
            'workspace' => $workspace,
            'channels' => $channels,
        ]);
    }

    public function select(Request $request): RedirectResponse
    {
        $request->validate([
            'channel_id' => 'required|string',
        ]);

        $oauthData = session('youtube_oauth');
        $workspaceId = session('social_connect_workspace');

        if (! $oauthData || ! $workspaceId) {
            return redirect()->route('workspaces.index')
                ->with('error', 'Session expired. Please try again.');
        }

        $workspace = Workspace::find($workspaceId);

        if (! $workspace || ! $request->user()->can('manageAccounts', $workspace)) {
            return redirect()->route('workspaces.index')
                ->with('error', 'Workspace not found.');
        }

        try {
            $channels = $this->fetchChannels($oauthData['access_token']);
            $selectedChannel = collect($channels)->firstWhere('id', $request->channel_id);

            if (! $selectedChannel) {
                return redirect()->route('social.youtube.select-channel')
                    ->with('error', 'Channel not found.');
            }

            $avatarPath = uploadFromUrl($selectedChannel['thumbnail']);

            $workspace->socialAccounts()->create([
                'platform' => $this->platform->value,
                'platform_user_id' => $selectedChannel['id'],
                'username' => $selectedChannel['custom_url'] ?? $selectedChannel['id'],
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

            session()->forget(['youtube_oauth', 'social_connect_workspace']);

            return redirect()->route('workspaces.accounts', $workspace)
                ->with('success', 'YouTube channel connected successfully!');
        } catch (\Exception $e) {
            Log::error('YouTube channel selection error', [
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('workspaces.accounts', $workspace)
                ->with('error', 'Error connecting channel. Please try again.');
        }
    }

    private function redirectToGoogle(Workspace $workspace): Response
    {
        return \Inertia\Inertia::location(
            Socialite::driver($this->driver)
                ->scopes($this->scopes)
                ->with([
                    'access_type' => 'offline',
                    'prompt' => 'consent',
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
