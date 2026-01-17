<?php

namespace App\Services\Social;

use App\Exceptions\TokenExpiredException;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TikTokPublisher
{
    /**
     * TikTok API error codes that indicate token issues.
     *
     * @see https://developers.tiktok.com/doc/tiktok-api-v2-error-handling
     */
    private const TOKEN_ERROR_CODES = [
        'access_token_invalid',
        'access_token_expired',
        'token_expired',
    ];

    private const TOKEN_ERROR_NUMERIC_CODES = [
        10001, // Invalid Access Token
        10002, // Access Token Expired
        10003, // Invalid Client Key
    ];

    private string $baseUrl = 'https://open.tiktokapis.com/v2';

    private string $accessToken;

    public function publish(PostPlatform $postPlatform): array
    {
        $account = $postPlatform->socialAccount;

        if ($account->is_token_expired || $account->is_token_expiring_soon) {
            $this->refreshToken($account);
            $account->refresh();
        }

        $this->accessToken = $account->access_token;

        $media = $postPlatform->media;

        if ($media->isEmpty()) {
            throw new \Exception('TikTok requires media (video or photos) to publish.');
        }

        $firstMedia = $media->first();
        $isVideo = str_starts_with($firstMedia->mime_type, 'video/');
        $isImage = str_starts_with($firstMedia->mime_type, 'image/');

        if ($isVideo) {
            return $this->publishVideo($postPlatform, $firstMedia);
        }

        if ($isImage) {
            return $this->publishPhotos($postPlatform, $media);
        }

        throw new \Exception('TikTok only supports video or image content.');
    }

    private function getHttpClient(): PendingRequest
    {
        return Http::withToken($this->accessToken)
            ->withHeaders([
                'Content-Type' => 'application/json; charset=UTF-8',
            ])
            ->timeout(120);
    }

    private function publishVideo(PostPlatform $postPlatform, $media): array
    {
        Log::info('TikTok publishing video', [
            'video_url' => $media->url,
            'media_full_url' => $media->full_url ?? $media->url,
            'content' => $postPlatform->content,
        ]);

        $response = $this->getHttpClient()
            ->post("{$this->baseUrl}/post/publish/video/init/", [
                'post_info' => [
                    'title' => $postPlatform->content,
                    'privacy_level' => 'SELF_ONLY',
                    'disable_duet' => false,
                    'disable_comment' => false,
                    'disable_stitch' => false,
                ],
                'source_info' => [
                    'source' => 'PULL_FROM_URL',
                    'video_url' => $media->url,
                ],
            ]);

        if ($response->failed()) {
            Log::error('TikTok video publish failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            $this->handleApiError($response, 'TikTok API error');
        }

        $data = $response->json();

        Log::info('TikTok video init response', ['data' => $data]);

        $publishId = $data['data']['publish_id'] ?? null;

        if (! $publishId) {
            throw new \Exception('TikTok did not return a publish_id');
        }

        // Wait for processing and get final status
        $finalStatus = $this->waitForPublishStatus($publishId);

        return [
            'id' => $publishId,
            'url' => $this->buildTikTokUrl($postPlatform->socialAccount),
        ];
    }

    private function publishPhotos(PostPlatform $postPlatform, $mediaCollection): array
    {
        $photoUrls = $mediaCollection
            ->filter(fn ($m) => str_starts_with($m->mime_type, 'image/'))
            ->map(fn ($m) => $m->url)
            ->values()
            ->toArray();

        if (empty($photoUrls)) {
            throw new \Exception('No valid images found for TikTok photo post');
        }

        Log::info('TikTok publishing photos', [
            'photo_urls' => $photoUrls,
            'photo_count' => count($photoUrls),
            'content' => $postPlatform->content,
        ]);

        $response = $this->getHttpClient()
            ->post("{$this->baseUrl}/post/publish/content/init/", [
                'post_info' => [
                    'title' => $postPlatform->content,
                    'privacy_level' => 'SELF_ONLY',
                    'disable_comment' => false,
                ],
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
                'body' => $response->body(),
            ]);
            $this->handleApiError($response, 'TikTok API error');
        }

        $data = $response->json();

        Log::info('TikTok photo init response', ['data' => $data]);

        $publishId = $data['data']['publish_id'] ?? null;

        if (! $publishId) {
            throw new \Exception('TikTok did not return a publish_id');
        }

        // Wait for processing and get final status
        $finalStatus = $this->waitForPublishStatus($publishId);

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
                    'body' => $response->body(),
                ]);

                continue;
            }

            $data = $response->json();
            $status = $data['data']['status'] ?? 'UNKNOWN';

            Log::info('TikTok publish status', [
                'status' => $status,
                'attempt' => $i,
                'data' => $data,
            ]);

            if ($status === 'PUBLISH_COMPLETE') {
                return $data['data'] ?? [];
            }

            if (in_array($status, ['FAILED', 'PUBLISH_FAILED'])) {
                $errorCode = $data['data']['fail_reason'] ?? 'Unknown error';
                throw new \Exception("TikTok publish failed: {$errorCode}");
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
            Log::error('TikTok token refresh failed', ['body' => $response->body()]);
            $this->handleApiError($response, 'Failed to refresh TikTok token');
        }

        $data = $response->json();

        $account->update([
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? $account->refresh_token,
            'token_expires_at' => isset($data['expires_in']) ? now()->addSeconds($data['expires_in']) : null,
        ]);

        Log::info('TikTok token refreshed successfully');
    }

    private function handleApiError(Response $response, string $context): void
    {
        $body = $response->json() ?? [];
        $error = $body['error'] ?? [];
        $errorCode = $error['code'] ?? $body['error']['code'] ?? null;
        $errorMessage = $error['message'] ?? $body['error']['message'] ?? $response->body();

        // TikTok can return error codes as strings or numeric codes
        $isTokenError = in_array($errorCode, self::TOKEN_ERROR_CODES)
            || in_array((int) $errorCode, self::TOKEN_ERROR_NUMERIC_CODES)
            || $response->status() === 401;

        if ($isTokenError) {
            throw new TokenExpiredException(
                "{$context}: {$errorMessage}",
                is_string($errorCode) ? $errorCode : (string) $errorCode
            );
        }

        throw new \Exception("{$context}: {$errorMessage}");
    }
}
