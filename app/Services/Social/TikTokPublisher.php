<?php

declare(strict_types=1);

namespace App\Services\Social;

use App\Exceptions\Social\TikTokPublishException;
use App\Exceptions\TokenExpiredException;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Services\Social\Concerns\HasSocialHttpClient;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TikTokPublisher
{
    use HasSocialHttpClient;

    private string $baseUrl = 'https://open.tiktokapis.com/v2';

    private string $accessToken;

    public function publish(PostPlatform $postPlatform): array
    {
        $this->validateContentLength($postPlatform);

        $content = $postPlatform->content ? app(ContentSanitizer::class)->sanitize($postPlatform->content, $postPlatform->platform) : null;

        $account = $postPlatform->socialAccount;

        if ($account->is_token_expired || $account->is_token_expiring_soon) {
            $this->refreshTokenWithLock($account, fn () => $this->refreshToken($account));
            $account->refresh();
        }

        $this->accessToken = $account->access_token;

        $media = $postPlatform->media;

        if ($media->isEmpty()) {
            throw new \Exception('TikTok requires media (video or photos) to publish.');
        }

        $firstMedia = $media->first();
        $isVideo = $firstMedia->isVideo();
        $isImage = $firstMedia->isImage();

        if ($isVideo) {
            return $this->publishVideo($postPlatform, $firstMedia, $content);
        }

        if ($isImage) {
            return $this->publishPhotos($postPlatform, $media, $content);
        }

        throw new \Exception('TikTok only supports video or image content.');
    }

    private function getHttpClient(): PendingRequest
    {
        return $this->socialHttp()->withToken($this->accessToken)
            ->withHeaders([
                'Content-Type' => 'application/json; charset=UTF-8',
            ]);
    }

    private function queryCreatorInfo(): array
    {
        $response = $this->getHttpClient()
            ->post("{$this->baseUrl}/post/publish/creator_info/query/", []);

        if ($response->failed()) {
            Log::warning('TikTok creator_info query failed', ['body' => $this->redactResponseBody($response->body())]);

            return ['privacy_level' => 'SELF_ONLY'];
        }

        $data = data_get($response->json(), 'data', []);
        $privacyOptions = data_get($data, 'privacy_level_options', ['SELF_ONLY']);

        // Prefer PUBLIC_TO_EVERYONE > MUTUAL_FOLLOW_FRIENDS > FOLLOWER_OF_CREATOR > SELF_ONLY
        $preferred = ['PUBLIC_TO_EVERYONE', 'MUTUAL_FOLLOW_FRIENDS', 'FOLLOWER_OF_CREATOR', 'SELF_ONLY'];

        $privacyLevel = 'SELF_ONLY';
        foreach ($preferred as $level) {
            if (in_array($level, $privacyOptions)) {
                $privacyLevel = $level;
                break;
            }
        }

        return [
            'privacy_level' => $privacyLevel,
            'max_video_post_duration_sec' => data_get($data, 'max_video_post_duration_sec'),
        ];
    }

    private function buildPostInfo(PostPlatform $postPlatform, ?string $content, array $creatorInfo): array
    {
        $meta = $postPlatform->meta ?? [];

        $privacyLevel = data_get($meta, 'privacy_level')
            ?: data_get($creatorInfo, 'privacy_level', 'SELF_ONLY');

        $postInfo = [
            'title' => $content ?? '',
            'privacy_level' => $privacyLevel,
            'disable_duet' => ! data_get($meta, 'allow_duet', false),
            'disable_comment' => ! data_get($meta, 'allow_comments', true),
            'disable_stitch' => ! data_get($meta, 'allow_stitch', false),
        ];

        if (data_get($meta, 'is_aigc', false)) {
            $postInfo['is_aigc'] = true;
        }

        if (data_get($meta, 'brand_content_toggle', false)) {
            $postInfo['brand_content_toggle'] = true;
        }

        if (data_get($meta, 'brand_organic_toggle', false)) {
            $postInfo['brand_organic_toggle'] = true;
        }

        return $postInfo;
    }

    private function publishVideo(PostPlatform $postPlatform, $media, ?string $content): array
    {
        $creatorInfo = $this->queryCreatorInfo();

        $response = $this->getHttpClient()
            ->post("{$this->baseUrl}/post/publish/video/init/", [
                'post_info' => $this->buildPostInfo($postPlatform, $content, $creatorInfo),
                'source_info' => [
                    'source' => 'PULL_FROM_URL',
                    'video_url' => $media->url,
                ],
            ]);

        if ($response->failed()) {
            Log::error('TikTok video publish failed', [
                'status' => $response->status(),
                'body' => $this->redactResponseBody($response->body()),
            ]);
            $this->handleApiError($response);
        }

        $data = $response->json();

        $publishId = data_get($data, 'data.publish_id');

        if (! $publishId) {
            throw new \Exception('TikTok did not return a publish_id');
        }

        // Wait for processing and get final status
        $this->waitForPublishStatus($publishId);

        return [
            'id' => $publishId,
            'url' => $this->buildTikTokUrl($postPlatform->socialAccount),
        ];
    }

    private function publishPhotos(PostPlatform $postPlatform, $mediaCollection, ?string $content): array
    {
        $photoUrls = $mediaCollection
            ->filter(fn ($m) => $m->isImage())
            ->map(fn ($m) => $m->url)
            ->values()
            ->toArray();

        if (empty($photoUrls)) {
            throw new \Exception('No valid images found for TikTok photo post');
        }

        $creatorInfo = $this->queryCreatorInfo();

        $postInfo = $this->buildPostInfo($postPlatform, $content, $creatorInfo);
        // Photos don't support duet/stitch/is_aigc
        unset($postInfo['disable_duet'], $postInfo['disable_stitch'], $postInfo['is_aigc']);

        // Auto add music is only for photos
        $meta = $postPlatform->meta ?? [];
        if (data_get($meta, 'auto_add_music', false)) {
            $postInfo['auto_add_music'] = true;
        }

        $response = $this->getHttpClient()
            ->post("{$this->baseUrl}/post/publish/content/init/", [
                'post_info' => $postInfo,
                'source_info' => [
                    'source' => 'PULL_FROM_URL',
                    'photo_cover_index' => 0,
                    'photo_images' => $photoUrls,
                ],
                'post_mode' => 'DIRECT_POST',
                'media_type' => 'PHOTO',
            ]);

        if ($response->failed()) {
            Log::error('TikTok photo publish failed', [
                'status' => $response->status(),
                'body' => $this->redactResponseBody($response->body()),
            ]);
            $this->handleApiError($response);
        }

        $data = $response->json();

        $publishId = data_get($data, 'data.publish_id');

        if (! $publishId) {
            throw new \Exception('TikTok did not return a publish_id');
        }

        // Wait for processing and get final status
        $this->waitForPublishStatus($publishId);

        return [
            'id' => $publishId,
            'url' => $this->buildTikTokUrl($postPlatform->socialAccount),
        ];
    }

    private function waitForPublishStatus(string $publishId, int $maxAttempts = 20): array
    {
        for ($i = 0; $i < $maxAttempts; $i++) {
            sleep(3);

            $response = $this->getHttpClient()
                ->post("{$this->baseUrl}/post/publish/status/fetch/", [
                    'publish_id' => $publishId,
                ]);

            if ($response->failed()) {
                Log::warning('TikTok status check failed', [
                    'attempt' => $i,
                    'body' => $this->redactResponseBody($response->body()),
                ]);

                continue;
            }

            $data = $response->json();
            $status = data_get($data, 'data.status', 'UNKNOWN');

            if ($status === 'PUBLISH_COMPLETE') {
                return data_get($data, 'data', []);
            }

            if (in_array($status, ['FAILED', 'PUBLISH_FAILED'])) {
                $failReason = data_get($data, 'data.fail_reason', 'Unknown error');
                throw TikTokPublishException::fromFailReason($failReason, json_encode($data));
            }

            // PROCESSING_UPLOAD, PROCESSING_DOWNLOAD, SENDING_TO_USER_INBOX - continue waiting
        }

        Log::warning('TikTok publish status timeout, returning publish_id anyway');

        return ['publish_id' => $publishId];
    }

    private function buildTikTokUrl(SocialAccount $account): ?string
    {
        $username = $account->username;

        if ($username) {
            return "https://www.tiktok.com/@{$username}";
        }

        return null;
    }

    private function refreshToken(SocialAccount $account): void
    {
        if (! $account->refresh_token) {
            throw new TokenExpiredException('No refresh token available for TikTok account');
        }

        $response = Http::asForm()->post('https://open.tiktokapis.com/v2/oauth/token/', [
            'client_key' => config('services.tiktok.client_id'),
            'client_secret' => config('services.tiktok.client_secret'),
            'grant_type' => 'refresh_token',
            'refresh_token' => $account->refresh_token,
        ]);

        if ($response->failed()) {
            Log::error('TikTok token refresh failed', ['body' => $this->redactResponseBody($response->body())]);
            $this->handleApiError($response);
        }

        $data = $response->json();

        $account->update([
            'access_token' => data_get($data, 'access_token'),
            'refresh_token' => data_get($data, 'refresh_token', $account->refresh_token),
            'token_expires_at' => data_get($data, 'expires_in') ? now()->addSeconds(data_get($data, 'expires_in')) : null,
        ]);

    }

    private function handleApiError(Response $response): never
    {
        throw TikTokPublishException::fromApiResponse($response);
    }
}
