<?php

declare(strict_types=1);

namespace App\Services\Social;

use App\Exceptions\TokenExpiredException;
use App\Models\SocialAccount;
use App\Services\Social\Concerns\HasSocialHttpClient;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TikTokCreatorInfo
{
    use HasSocialHttpClient;

    private string $baseUrl;

    private string $accessToken;

    public function __construct()
    {
        $this->baseUrl = config('trypost.platforms.tiktok.api');
    }

    /**
     * @return array{
     *     creator_nickname: ?string,
     *     creator_username: ?string,
     *     creator_avatar_url: ?string,
     *     privacy_level_options: array<int, string>,
     *     comment_disabled: bool,
     *     duet_disabled: bool,
     *     stitch_disabled: bool,
     *     max_video_post_duration_sec: ?int,
     * }
     */
    public function fetch(SocialAccount $account): array
    {
        if ($account->is_token_expired || $account->is_token_expiring_soon) {
            $this->refreshTokenWithLock($account, fn () => $this->refreshToken($account));
            $account->refresh();
        }

        $this->accessToken = $account->access_token;

        $response = $this->getHttpClient()
            ->post("{$this->baseUrl}/post/publish/creator_info/query/", []);

        if ($response->failed()) {
            Log::warning('TikTok creator_info query failed', [
                'social_account_id' => $account->id,
                'body' => $this->redactResponseBody($response->body()),
            ]);

            return $this->emptyPayload();
        }

        $data = data_get($response->json(), 'data', []);

        return [
            'creator_nickname' => data_get($data, 'creator_nickname'),
            'creator_username' => data_get($data, 'creator_username'),
            'creator_avatar_url' => data_get($data, 'creator_avatar_url'),
            'privacy_level_options' => data_get($data, 'privacy_level_options', []),
            'comment_disabled' => (bool) data_get($data, 'comment_disabled', false),
            'duet_disabled' => (bool) data_get($data, 'duet_disabled', false),
            'stitch_disabled' => (bool) data_get($data, 'stitch_disabled', false),
            'max_video_post_duration_sec' => data_get($data, 'max_video_post_duration_sec'),
        ];
    }

    /**
     * @return array{
     *     creator_nickname: null,
     *     creator_username: null,
     *     creator_avatar_url: null,
     *     privacy_level_options: array<int, string>,
     *     comment_disabled: bool,
     *     duet_disabled: bool,
     *     stitch_disabled: bool,
     *     max_video_post_duration_sec: null,
     * }
     */
    private function emptyPayload(): array
    {
        return [
            'creator_nickname' => null,
            'creator_username' => null,
            'creator_avatar_url' => null,
            'privacy_level_options' => [],
            'comment_disabled' => false,
            'duet_disabled' => false,
            'stitch_disabled' => false,
            'max_video_post_duration_sec' => null,
        ];
    }

    private function getHttpClient(): PendingRequest
    {
        return $this->socialHttp()->asJson()->withToken($this->accessToken);
    }

    private function refreshToken(SocialAccount $account): void
    {
        if (! $account->refresh_token) {
            throw new TokenExpiredException('No refresh token available for TikTok account');
        }

        $response = Http::asForm()->post(config('trypost.platforms.tiktok.api').'/oauth/token/', [
            'client_key' => config('services.tiktok.client_id'),
            'client_secret' => config('services.tiktok.client_secret'),
            'grant_type' => 'refresh_token',
            'refresh_token' => $account->refresh_token,
        ]);

        if ($response->failed()) {
            Log::error('TikTok token refresh failed', ['body' => $this->redactResponseBody($response->body())]);
            throw new TokenExpiredException('TikTok token refresh failed');
        }

        $data = $response->json();

        $account->update([
            'access_token' => data_get($data, 'access_token'),
            'refresh_token' => data_get($data, 'refresh_token', $account->refresh_token),
            'token_expires_at' => data_get($data, 'expires_in') ? now()->addSeconds(data_get($data, 'expires_in')) : null,
        ]);
    }
}
