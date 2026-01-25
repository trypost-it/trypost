<?php

namespace App\Services\Social;

use App\Enums\SocialAccount\Platform;
use App\Exceptions\TokenExpiredException;
use App\Models\SocialAccount;
use Illuminate\Support\Facades\Http;

class ConnectionVerifier
{
    /**
     * Verify that a social account connection is still valid.
     *
     * @throws TokenExpiredException if the connection is invalid
     */
    public function verify(SocialAccount $account): bool
    {
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
