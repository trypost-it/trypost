<?php

declare(strict_types=1);

namespace App\Services\Social;

use App\Enums\SocialAccount\Platform;
use App\Exceptions\TokenExpiredException;
use App\Models\SocialAccount;
use Illuminate\Support\Facades\Cache;
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
            Platform::Instagram, Platform::InstagramFacebook => $this->verifyInstagram($account),
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
        $lock = Cache::lock("token_refresh:{$account->id}", 30);

        if (! $lock->get()) {
            // Another process is already refreshing this token
            $account->refresh();

            return;
        }

        try {
            match ($account->platform) {
                Platform::LinkedIn, Platform::LinkedInPage => $this->refreshLinkedInToken($account),
                Platform::X => $this->refreshXToken($account),
                Platform::Bluesky => $this->refreshBlueskyToken($account),
                Platform::YouTube => $this->refreshYouTubeToken($account),
                Platform::TikTok => $this->refreshTikTokToken($account),
                Platform::Pinterest => $this->refreshPinterestToken($account),
                Platform::Threads => $this->refreshThreadsToken($account),
                Platform::Instagram => $this->refreshInstagramToken($account),
                // InstagramFacebook uses page tokens that don't expire (like Facebook)
                // Mastodon tokens don't expire
                // Mastodon tokens don't expire
                default => null,
            };
        } finally {
            $lock->release();
        }
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
            Log::error('ConnectionVerifier: LinkedIn token refresh failed', ['body' => $this->redactBody($response->body())]);
            throw new TokenExpiredException('Failed to refresh LinkedIn token');
        }

        $data = $response->json();

        $account->update([
            'access_token' => data_get($data, 'access_token'),
            'refresh_token' => data_get($data, 'refresh_token', $account->refresh_token),
            'token_expires_at' => data_get($data, 'expires_in') ? now()->addSeconds(data_get($data, 'expires_in')) : null,
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
            ->post(config('trypost.platforms.x.api').'/oauth2/token', [
                'grant_type' => 'refresh_token',
                'refresh_token' => $account->refresh_token,
            ]);

        if ($response->failed()) {
            Log::error('ConnectionVerifier: X token refresh failed', ['body' => $this->redactBody($response->body())]);
            throw new TokenExpiredException('Failed to refresh X token');
        }

        $data = $response->json();

        $account->update([
            'access_token' => data_get($data, 'access_token'),
            'refresh_token' => data_get($data, 'refresh_token', $account->refresh_token),
            'token_expires_at' => now()->addSeconds(data_get($data, 'expires_in', 7200)),
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
                'access_token' => data_get($data, 'accessJwt'),
                'refresh_token' => data_get($data, 'refreshJwt'),
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
                        'access_token' => data_get($data, 'accessJwt'),
                        'refresh_token' => data_get($data, 'refreshJwt'),
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
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
        ]);

        if ($response->failed()) {
            Log::error('ConnectionVerifier: YouTube token refresh failed', ['body' => $this->redactBody($response->body())]);
            throw new TokenExpiredException('Failed to refresh YouTube token');
        }

        $data = $response->json();

        $account->update([
            'access_token' => data_get($data, 'access_token'),
            'token_expires_at' => data_get($data, 'expires_in') ? now()->addSeconds(data_get($data, 'expires_in')) : null,
        ]);

        $account->refresh();
    }

    private function refreshTikTokToken(SocialAccount $account): void
    {
        if (! $account->refresh_token) {
            throw new TokenExpiredException('No refresh token available for TikTok account');
        }

        $response = Http::asForm()->post(config('trypost.platforms.tiktok.api').'/oauth/token/', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $account->refresh_token,
            'client_key' => config('services.tiktok.client_id'),
            'client_secret' => config('services.tiktok.client_secret'),
        ]);

        if ($response->failed()) {
            Log::error('ConnectionVerifier: TikTok token refresh failed', ['body' => $this->redactBody($response->body())]);
            throw new TokenExpiredException('Failed to refresh TikTok token');
        }

        $data = $response->json();

        $account->update([
            'access_token' => data_get($data, 'access_token'),
            'refresh_token' => data_get($data, 'refresh_token', $account->refresh_token),
            'token_expires_at' => data_get($data, 'expires_in') ? now()->addSeconds(data_get($data, 'expires_in')) : null,
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
        ])->asForm()->post(config('trypost.platforms.pinterest.api').'/oauth/token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $account->refresh_token,
        ]);

        if ($response->failed()) {
            Log::error('ConnectionVerifier: Pinterest token refresh failed', ['body' => $this->redactBody($response->body())]);
            throw new TokenExpiredException('Failed to refresh Pinterest token');
        }

        $data = $response->json();

        $account->update([
            'access_token' => data_get($data, 'access_token'),
            'refresh_token' => data_get($data, 'refresh_token', $account->refresh_token),
            'token_expires_at' => data_get($data, 'expires_in') ? now()->addSeconds(data_get($data, 'expires_in')) : null,
        ]);

        $account->refresh();
    }

    private function refreshThreadsToken(SocialAccount $account): void
    {
        // Threads uses long-lived tokens that can be refreshed
        $response = Http::get(config('trypost.platforms.threads.auth_api').'/refresh_access_token', [
            'grant_type' => 'th_refresh_token',
            'access_token' => $account->access_token,
        ]);

        if ($response->failed()) {
            Log::error('ConnectionVerifier: Threads token refresh failed', ['body' => $this->redactBody($response->body())]);
            throw new TokenExpiredException('Failed to refresh Threads token');
        }

        $data = $response->json();
        $newToken = data_get($data, 'access_token');

        $account->update([
            'access_token' => $newToken,
            'refresh_token' => $newToken,
            'token_expires_at' => data_get($data, 'expires_in') ? now()->addSeconds(data_get($data, 'expires_in')) : null,
        ]);

        $account->refresh();
    }

    private function refreshInstagramToken(SocialAccount $account): void
    {
        $response = Http::get(config('trypost.platforms.instagram.auth_api').'/refresh_access_token', [
            'grant_type' => 'ig_refresh_token',
            'access_token' => $account->access_token,
        ]);

        if ($response->failed()) {
            Log::error('ConnectionVerifier: Instagram token refresh failed', ['body' => $this->redactBody($response->body())]);
            throw new TokenExpiredException('Failed to refresh Instagram token');
        }

        $data = $response->json();
        $newToken = data_get($data, 'access_token');

        $account->update([
            'access_token' => $newToken,
            'refresh_token' => $newToken,
            'token_expires_at' => data_get($data, 'expires_in') ? now()->addSeconds(data_get($data, 'expires_in')) : null,
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
            ->get(config('trypost.platforms.linkedin.api').'/rest/userinfo');

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
            ->get(config('trypost.platforms.linkedin-page.api').'/rest/organizationAcls', [
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
            ->get(config('trypost.platforms.x.api').'/users/me');

        if ($response->status() === 401) {
            throw new TokenExpiredException('X access token is invalid or expired');
        }

        return $response->successful();
    }

    private function verifyInstagram(SocialAccount $account): bool
    {
        $response = Http::get(config('trypost.platforms.instagram.graph_api').'/me', [
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
        $response = Http::get(config('trypost.platforms.facebook.graph_api').'/me', [
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
        $response = Http::get(config('trypost.platforms.threads.graph_api').'/me', [
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
            ->get(config('trypost.platforms.tiktok.api').'/user/info/', [
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
            ->get(config('trypost.platforms.youtube.data_api').'/channels', [
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
            ->get(config('trypost.platforms.pinterest.api').'/user_account');

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

    private function redactBody(string $body): string
    {
        return preg_replace(
            [
                '/access_token=([^&"\s]+)/',
                '/"access_token"\s*:\s*"([^"]+)"/',
                '/Bearer\s+\S+/',
                '/"token"\s*:\s*"([^"]+)"/',
            ],
            [
                'access_token=[REDACTED]',
                '"access_token":"[REDACTED]"',
                'Bearer [REDACTED]',
                '"token":"[REDACTED]"',
            ],
            $body
        );
    }
}
