<?php

namespace App\Http\Controllers\Auth;

use App\Enums\SocialPlatform;
use App\Enums\Status;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class ThreadsController extends SocialController
{
    protected SocialPlatform $platform = SocialPlatform::Threads;

    protected array $scopes = [
        'threads_basic',
        'threads_content_publish',
        'threads_manage_replies',
        'threads_read_replies',
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
        ]);

        $state = bin2hex(random_bytes(16));
        session(['threads_oauth_state' => $state]);

        $params = http_build_query([
            'client_id' => config('services.threads.client_id'),
            'redirect_uri' => config('services.threads.redirect'),
            'scope' => implode(',', $this->scopes),
            'response_type' => 'code',
            'state' => $state,
        ]);

        return Inertia::location("https://threads.net/oauth/authorize?{$params}");
    }

    public function callback(Request $request): View
    {
        $workspaceId = session('social_connect_workspace');
        $savedState = session('threads_oauth_state');

        if (! $workspaceId) {
            session()->forget(['threads_oauth_state', 'social_reconnect_id']);

            return $this->popupCallback(false, 'Session expired. Please try again.', $this->platform->value);
        }

        if ($request->state !== $savedState) {
            session()->forget(['threads_oauth_state', 'social_reconnect_id']);

            return $this->popupCallback(false, 'Invalid state. Please try again.', $this->platform->value);
        }

        $workspace = Workspace::find($workspaceId);

        if (! $workspace || ! $request->user()->can('manageAccounts', $workspace)) {
            session()->forget(['threads_oauth_state', 'social_reconnect_id']);

            return $this->popupCallback(false, 'Workspace not found.', $this->platform->value);
        }

        $reconnectId = session('social_reconnect_id');
        $existingAccount = $reconnectId ? $workspace->socialAccounts()->find($reconnectId) : null;

        // If account exists and is connected, don't allow duplicate
        if (! $existingAccount && $workspace->hasConnectedPlatform($this->platform->value)) {
            session()->forget(['threads_oauth_state', 'social_reconnect_id']);

            return $this->popupCallback(false, 'This platform is already connected.', $this->platform->value);
        }

        try {
            // Exchange code for short-lived token
            $tokenResponse = Http::asForm()->post('https://graph.threads.net/oauth/access_token', [
                'client_id' => config('services.threads.client_id'),
                'client_secret' => config('services.threads.client_secret'),
                'grant_type' => 'authorization_code',
                'redirect_uri' => config('services.threads.redirect'),
                'code' => $request->code,
            ]);

            if ($tokenResponse->failed()) {
                Log::error('Threads token exchange failed', [
                    'status' => $tokenResponse->status(),
                    'body' => $tokenResponse->body(),
                ]);
                throw new \Exception('Failed to exchange token');
            }

            $tokenData = $tokenResponse->json();
            $shortLivedToken = $tokenData['access_token'];
            $userId = $tokenData['user_id'];

            // Exchange for long-lived token
            $longLivedResponse = Http::get('https://graph.threads.net/access_token', [
                'grant_type' => 'th_exchange_token',
                'client_secret' => config('services.threads.client_secret'),
                'access_token' => $shortLivedToken,
            ]);

            $longLivedToken = $shortLivedToken;
            $expiresIn = null;

            if ($longLivedResponse->successful()) {
                $longLivedData = $longLivedResponse->json();
                $longLivedToken = $longLivedData['access_token'] ?? $shortLivedToken;
                $expiresIn = $longLivedData['expires_in'] ?? null;
            }

            // Fetch user profile
            $profileResponse = Http::get("https://graph.threads.net/v1.0/{$userId}", [
                'access_token' => $longLivedToken,
                'fields' => 'id,username,name,threads_profile_picture_url',
            ]);

            if ($profileResponse->failed()) {
                Log::error('Threads profile fetch failed', [
                    'body' => $profileResponse->body(),
                ]);
                throw new \Exception('Failed to fetch profile');
            }

            $profile = $profileResponse->json();
            $avatarPath = uploadFromUrl($profile['threads_profile_picture_url'] ?? null);

            if ($existingAccount) {
                // Reconnect existing account
                $existingAccount->update([
                    'platform_user_id' => $profile['id'],
                    'username' => $profile['username'],
                    'display_name' => $profile['name'] ?? $profile['username'],
                    'avatar_url' => $avatarPath,
                    'access_token' => $longLivedToken,
                    'refresh_token' => null,
                    'token_expires_at' => $expiresIn ? now()->addSeconds($expiresIn) : null,
                    'scopes' => $this->scopes,
                ]);
                $existingAccount->markAsConnected();

                session()->forget(['threads_oauth_state', 'social_reconnect_id']);

                return $this->popupCallback(true, 'Threads account reconnected!', $this->platform->value);
            }

            // Create new account
            $workspace->socialAccounts()->create([
                'platform' => $this->platform->value,
                'platform_user_id' => $profile['id'],
                'username' => $profile['username'],
                'display_name' => $profile['name'] ?? $profile['username'],
                'avatar_url' => $avatarPath,
                'access_token' => $longLivedToken,
                'refresh_token' => null,
                'token_expires_at' => $expiresIn ? now()->addSeconds($expiresIn) : null,
                'scopes' => $this->scopes,
                'status' => Status::Connected,
            ]);

            session()->forget(['threads_oauth_state', 'social_reconnect_id']);

            return $this->popupCallback(true, 'Threads account connected!', $this->platform->value);
        } catch (\Exception $e) {
            Log::error('Threads OAuth Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            session()->forget(['threads_oauth_state', 'social_reconnect_id']);

            return $this->popupCallback(false, 'Error connecting account. Please try again.', $this->platform->value);
        }
    }
}
