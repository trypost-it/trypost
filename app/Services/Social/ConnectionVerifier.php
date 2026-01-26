<?php

namespace App\Services\Social;

use App\Enums\SocialAccount\Platform;
use App\Exceptions\TokenExpiredException;
use App\Models\SocialAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ConnectionVerifier
{
    /**
     * Verify that a social account connection is still valid.
     *
     * @throws TokenExpiredException if the connection is invalid
     */
    public function verify(SocialAccount $account): bool
    {
        // Refresh token if expired or expiring soon before verifying
        if ($account->is_token_expired || $account->is_token_expiring_soon) {
            $this->refreshTokenIfNeeded($account);
        }

        return match ($account->platform) {
            Platform::LinkedIn => $this->verifyLinkedIn($account),
            Platform::LinkedInPage => $this->verifyLinkedInPage($account),
            Platform::X => $this->verifyX($account),
            Platform::Instagram => $this->verifyInstagram($account),
            Platform::Facebook => $this->verifyFacebook($account),
            Platform::Threads => $this->verifyThreads($account),
            Platform::TikTok => $this->verifyTikTok($account),
            Platform::YouTube => $this->verifyYouTube($account),
            Platform::Pinterest => $this->verifyPinterest($account),
            Platform::Bluesky => $this->verifyBluesky($account),
            Platform::Mastodon => $this->verifyMastodon($account),
        };
    }

    /**
     * Refresh token based on platform type.
     *
     * @throws TokenExpiredException if refresh fails
     */
    private function refreshTokenIfNeeded(SocialAccount $account): void
    {
        match ($account->platform) {
            Platform::LinkedIn, Platform::LinkedInPage => $this->refreshLinkedInToken($account),
            Platform::X => $this->refreshXToken($account),
            Platform::Bluesky => $this->refreshBlueskyToken($account),
            Platform::YouTube => $this->refreshYouTubeToken($account),
            Platform::TikTok => $this->refreshTikTokToken($account),
            Platform::Pinterest => $this->refreshPinterestToken($account),
            Platform::Threads => $this->refreshThreadsToken($account),
            // Facebook, Instagram use long-lived tokens without refresh mechanism
            // Mastodon tokens don't expire
            default => null,
        };
    }

    private function refreshLinkedInToken(SocialAccount $account): void
    {
        if (! $account->refresh_token) {
            throw new TokenExpiredException('No refresh token available for LinkedIn account');
        }

        $response = Http::asForm()->post('https://www.linkedin.com/oauth/v2/accessToken', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $account->refresh_token,
            'client_id' => config('services.linkedin.client_id'),
            'client_secret' => config('services.linkedin.client_secret'),
        ]);

        if ($response->failed()) {
            Log::error('ConnectionVerifier: LinkedIn token refresh failed', ['body' => $response->body()]);
            throw new TokenExpiredException('Failed to refresh LinkedIn token');
        }

        $data = $response->json();

        $account->update([
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? $account->refresh_token,
            'token_expires_at' => isset($data['expires_in']) ? now()->addSeconds($data['expires_in']) : null,
        ]);

        $account->refresh();

        // Sync tokens between LinkedIn personal and LinkedIn Page
        app(LinkedInTokenSynchronizer::class)->syncTokens($account);
    }

    private function refreshXToken(SocialAccount $account): void
    {
        if (! $account->refresh_token) {
            throw new TokenExpiredException('No refresh token available for X account');
        }

        $response = Http::asForm()
            ->withBasicAuth(config('services.x.client_id'), config('services.x.client_secret'))
            ->post('https://api.x.com/2/oauth2/token', [
                'grant_type' => 'refresh_token',
                'refresh_token' => $account->refresh_token,
            ]);

        if ($response->failed()) {
            Log::error('ConnectionVerifier: X token refresh failed', ['body' => $response->body()]);
            throw new TokenExpiredException('Failed to refresh X token');
        }

        $data = $response->json();

        $account->update([
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? $account->refresh_token,
            'token_expires_at' => now()->addSeconds($data['expires_in'] ?? 7200),
        ]);

        $account->refresh();
    }

    private function refreshBlueskyToken(SocialAccount $account): void
    {
        $service = $account->meta['service'] ?? 'https://bsky.social';

        // Try refresh token first
        $response = Http::withToken($account->refresh_token)
            ->post("{$service}/xrpc/com.atproto.server.refreshSession");

        if ($response->successful()) {
            $data = $response->json();
            $account->update([
                'access_token' => $data['accessJwt'],
                'refresh_token' => $data['refreshJwt'],
                'token_expires_at' => now()->addHours(2),
            ]);

            $account->refresh();

            return;
        }

        // If refresh fails, re-authenticate with stored credentials
        if (isset($account->meta['password'])) {
            try {
                $password = decrypt($account->meta['password']);
                $identifier = $account->meta['identifier'];

                $response = Http::post("{$service}/xrpc/com.atproto.server.createSession", [
                    'identifier' => $identifier,
                    'password' => $password,
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    $account->update([
                        'access_token' => $data['accessJwt'],
                        'refresh_token' => $data['refreshJwt'],
                        'token_expires_at' => now()->addHours(2),
                    ]);

                    $account->refresh();

                    return;
                }
            } catch (\Exception $e) {
                Log::error('ConnectionVerifier: Bluesky re-authentication failed', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        throw new TokenExpiredException('Bluesky session expired');
    }

    private function refreshYouTubeToken(SocialAccount $account): void
    {
        if (! $account->refresh_token) {
            throw new TokenExpiredException('No refresh token available for YouTube account');
        }

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $account->refresh_token,
            'client_id' => config('services.youtube.client_id'),
            'client_secret' => config('services.youtube.client_secret'),
        ]);

        if ($response->failed()) {
            Log::error('ConnectionVerifier: YouTube token refresh failed', ['body' => $response->body()]);
            throw new TokenExpiredException('Failed to refresh YouTube token');
        }

        $data = $response->json();

        $account->update([
            'access_token' => $data['access_token'],
            'token_expires_at' => isset($data['expires_in']) ? now()->addSeconds($data['expires_in']) : null,
        ]);

        $account->refresh();
    }

    private function refreshTikTokToken(SocialAccount $account): void
    {
        if (! $account->refresh_token) {
            throw new TokenExpiredException('No refresh token available for TikTok account');
        }

        $response = Http::asForm()->post('https://open.tiktokapis.com/v2/oauth/token/', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $account->refresh_token,
            'client_key' => config('services.tiktok.client_id'),
            'client_secret' => config('services.tiktok.client_secret'),
        ]);

        if ($response->failed()) {
            Log::error('ConnectionVerifier: TikTok token refresh failed', ['body' => $response->body()]);
            throw new TokenExpiredException('Failed to refresh TikTok token');
        }

        $data = $response->json();

        $account->update([
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? $account->refresh_token,
            'token_expires_at' => isset($data['expires_in']) ? now()->addSeconds($data['expires_in']) : null,
        ]);

        $account->refresh();
    }

    private function refreshPinterestToken(SocialAccount $account): void
    {
        if (! $account->refresh_token) {
            throw new TokenExpiredException('No refresh token available for Pinterest account');
        }

        $credentials = base64_encode(config('services.pinterest.client_id').':'.config('services.pinterest.client_secret'));

        $response = Http::withHeaders([
            'Authorization' => "Basic {$credentials}",
            'Content-Type' => 'application/x-www-form-urlencoded',
        ])->asForm()->post('https://api.pinterest.com/v5/oauth/token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $account->refresh_token,
        ]);

        if ($response->failed()) {
            Log::error('ConnectionVerifier: Pinterest token refresh failed', ['body' => $response->body()]);
            throw new TokenExpiredException('Failed to refresh Pinterest token');
        }

        $data = $response->json();

        $account->update([
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? $account->refresh_token,
            'token_expires_at' => isset($data['expires_in']) ? now()->addSeconds($data['expires_in']) : null,
        ]);

        $account->refresh();
    }

    private function refreshThreadsToken(SocialAccount $account): void
    {
        // Threads uses long-lived tokens that can be refreshed
        $response = Http::get('https://graph.threads.net/refresh_access_token', [
            'grant_type' => 'th_refresh_token',
            'access_token' => $account->access_token,
        ]);

        if ($response->failed()) {
            Log::error('ConnectionVerifier: Threads token refresh failed', ['body' => $response->body()]);
            throw new TokenExpiredException('Failed to refresh Threads token');
        }

        $data = $response->json();

        $account->update([
            'access_token' => $data['access_token'],
            'token_expires_at' => isset($data['expires_in']) ? now()->addSeconds($data['expires_in']) : null,
        ]);

        $account->refresh();
    }

    private function verifyLinkedIn(SocialAccount $account): bool
    {
        $response = Http::withToken($account->access_token)
            ->withHeaders([
                'X-Restli-Protocol-Version' => '2.0.0',
                'LinkedIn-Version' => '202601',
            ])
            ->get('https://api.linkedin.com/rest/userinfo');

        if ($response->status() === 401) {
            throw new TokenExpiredException('LinkedIn access token is invalid or expired');
        }

        return $response->successful();
    }

    private function verifyLinkedInPage(SocialAccount $account): bool
    {
        $response = Http::withToken($account->access_token)
            ->withHeaders([
                'X-Restli-Protocol-Version' => '2.0.0',
                'LinkedIn-Version' => '202601',
            ])
            ->get('https://api.linkedin.com/rest/organizationAcls', [
                'q' => 'roleAssignee',
            ]);

        if ($response->status() === 401) {
            throw new TokenExpiredException('LinkedIn Page access token is invalid or expired');
        }

        return $response->successful();
    }

    private function verifyX(SocialAccount $account): bool
    {
        $response = Http::withToken($account->access_token)
            ->get('https://api.x.com/2/users/me');

        if ($response->status() === 401) {
            throw new TokenExpiredException('X access token is invalid or expired');
        }

        return $response->successful();
    }

    private function verifyInstagram(SocialAccount $account): bool
    {
        $response = Http::get('https://graph.instagram.com/v24.0/me', [
            'fields' => 'id,username',
            'access_token' => $account->access_token,
        ]);

        $body = $response->json() ?? [];

        if (isset($body['error'])) {
            $errorCode = $body['error']['code'] ?? null;
            $errorType = $body['error']['type'] ?? null;

            if ($errorType === 'OAuthException' || $errorCode === 190) {
                throw new TokenExpiredException('Instagram access token is invalid or expired');
            }
        }

        return $response->successful();
    }

    private function verifyFacebook(SocialAccount $account): bool
    {
        $response = Http::get('https://graph.facebook.com/v24.0/me', [
            'fields' => 'id,name',
            'access_token' => $account->access_token,
        ]);

        $body = $response->json() ?? [];

        if (isset($body['error'])) {
            $errorCode = $body['error']['code'] ?? null;
            $errorType = $body['error']['type'] ?? null;

            if ($errorType === 'OAuthException' || $errorCode === 190) {
                throw new TokenExpiredException('Facebook access token is invalid or expired');
            }
        }

        return $response->successful();
    }

    private function verifyThreads(SocialAccount $account): bool
    {
        $response = Http::get('https://graph.threads.net/v1.0/me', [
            'fields' => 'id,username',
            'access_token' => $account->access_token,
        ]);

        $body = $response->json() ?? [];

        if (isset($body['error'])) {
            $errorCode = $body['error']['code'] ?? null;
            $errorType = $body['error']['type'] ?? null;

            if ($errorType === 'OAuthException' || $errorCode === 190) {
                throw new TokenExpiredException('Threads access token is invalid or expired');
            }
        }

        return $response->successful();
    }

    private function verifyTikTok(SocialAccount $account): bool
    {
        $response = Http::withToken($account->access_token)
            ->withHeaders([
                'Content-Type' => 'application/json',
            ])
            ->get('https://open.tiktokapis.com/v2/user/info/', [
                'fields' => 'open_id,display_name',
            ]);

        $body = $response->json() ?? [];
        $errorCode = $body['error']['code'] ?? null;

        if ($response->status() === 401 || in_array($errorCode, ['access_token_invalid', 'access_token_expired', 10001, 10002])) {
            throw new TokenExpiredException('TikTok access token is invalid or expired');
        }

        return $response->successful();
    }

    private function verifyYouTube(SocialAccount $account): bool
    {
        $response = Http::withToken($account->access_token)
            ->get('https://www.googleapis.com/youtube/v3/channels', [
                'part' => 'id',
                'mine' => 'true',
            ]);

        if ($response->status() === 401) {
            throw new TokenExpiredException('YouTube access token is invalid or expired');
        }

        return $response->successful();
    }

    private function verifyPinterest(SocialAccount $account): bool
    {
        $response = Http::withToken($account->access_token)
            ->get('https://api.pinterest.com/v5/user_account');

        if ($response->status() === 401) {
            throw new TokenExpiredException('Pinterest access token is invalid or expired');
        }

        return $response->successful();
    }

    private function verifyBluesky(SocialAccount $account): bool
    {
        $service = $account->meta['service'] ?? 'https://bsky.social';

        $response = Http::withToken($account->access_token)
            ->get("{$service}/xrpc/app.bsky.actor.getProfile", [
                'actor' => $account->platform_user_id,
            ]);

        $body = $response->json() ?? [];
        $error = $body['error'] ?? null;

        if ($error === 'ExpiredToken' || $error === 'InvalidToken') {
            throw new TokenExpiredException('Bluesky access token is invalid or expired');
        }

        return $response->successful();
    }

    private function verifyMastodon(SocialAccount $account): bool
    {
        $instance = $account->meta['instance'] ?? 'https://mastodon.social';

        $response = Http::withToken($account->access_token)
            ->get("{$instance}/api/v1/accounts/verify_credentials");

        if ($response->status() === 401 || $response->status() === 403) {
            throw new TokenExpiredException('Mastodon access token is invalid or expired');
        }

        return $response->successful();
    }
}
