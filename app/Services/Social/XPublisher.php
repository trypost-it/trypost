<?php

namespace App\Services\Social;

use App\Exceptions\TokenExpiredException;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class XPublisher
{
    /**
     * X/Twitter API error titles that indicate token issues.
     *
     * @see https://developer.twitter.com/en/support/twitter-api/error-troubleshooting
     */
    private const TOKEN_ERROR_TITLES = [
        'Unauthorized',
    ];

    private string $baseUrl = 'https://api.x.com';

    private string $accessToken;

    public function publish(PostPlatform $postPlatform): array
    {
        $account = $postPlatform->socialAccount;

        // Refresh token if expired or expiring soon
        if ($account->is_token_expired || $account->is_token_expiring_soon) {
            $this->refreshToken($account);
            $account->refresh();
        }

        $this->accessToken = $account->access_token;

        $data = [
            'text' => $postPlatform->content,
        ];

        $mediaIds = [];
        $media = $postPlatform->media;

        if ($media->isNotEmpty()) {
            foreach ($media as $mediaItem) {
                Log::info('Uploading media to X', [
                    'url' => $mediaItem->url,
                    'mime_type' => $mediaItem->mime_type,
                ]);

                $uploadedMedia = $this->uploadMedia($mediaItem);

                Log::info('X media upload response', ['response' => $uploadedMedia]);

                // v2 API returns data.id, v1 returns media_id
                $mediaId = $uploadedMedia['data']['id'] ?? $uploadedMedia['media_id'] ?? null;
                if ($mediaId) {
                    $mediaIds[] = $mediaId;
                }
            }
        }

        if (! empty($mediaIds)) {
            $data['media'] = [
                'media_ids' => $mediaIds,
            ];
        }

        Log::info('Posting tweet', ['data' => $data]);

        $response = $this->getHttpClient()
            ->post("{$this->baseUrl}/2/tweets", $data);

        if ($response->failed()) {
            Log::error('X post creation failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            $this->handleApiError($response, 'X API error');
        }

        $responseData = $response->json();
        $tweetId = $responseData['data']['id'] ?? null;

        return [
            'id' => $tweetId ?? 'unknown',
            'url' => $tweetId ? "https://x.com/{$account->username}/status/{$tweetId}" : null,
        ];
    }

    private function getHttpClient(): PendingRequest
    {
        return Http::withToken($this->accessToken)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
            ->timeout(360);
    }

    private function uploadMedia($mediaItem): ?array
    {
        $mediaContent = file_get_contents($mediaItem->url);
        $mimeType = $mediaItem->mime_type;
        $fileSize = strlen($mediaContent);

        // Create temp file
        $tempFile = tempnam(sys_get_temp_dir(), 'x_media_');
        file_put_contents($tempFile, $mediaContent);

        try {
            $mediaCategory = $this->getMediaCategory($mimeType, $fileSize);

            $isVideo = str_starts_with($mimeType, 'video/');
            $isGif = $mimeType === 'image/gif';

            // Use chunked upload for:
            // - Videos (always)
            // - GIFs (need async processing)
            // - Files > 5MB (API limit for simple upload)
            $useChunkedUpload = $isVideo || $isGif || $fileSize > 5 * 1024 * 1024;

            if ($useChunkedUpload) {
                return $this->chunkedUpload($mediaContent, $mimeType, $mediaCategory);
            }

            // Simple upload for small images
            $response = Http::withToken($this->accessToken)
                ->timeout(360)
                ->attach(
                    'media',
                    file_get_contents($tempFile),
                    basename($tempFile),
                    ['Content-Type' => $mimeType]
                );

            $formParams = [];
            if ($mediaCategory) {
                $formParams['media_category'] = $mediaCategory;
            }

            $response = $response->post("{$this->baseUrl}/2/media/upload", $formParams);

            if ($response->failed()) {
                Log::error('X media upload error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                $this->handleApiError($response, 'Failed to upload media');
            }

            $responseData = $response->json();

            // v2 API returns data.id
            $mediaId = $responseData['data']['id'] ?? $responseData['media_id'] ?? null;

            if ($isGif && $mediaId) {
                $this->waitForProcessing($mediaId);
            }

            return $responseData;
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    private function chunkedUpload(string $mediaContent, string $mimeType, string $mediaCategory): array
    {
        $totalBytes = strlen($mediaContent);

        Log::info('X chunked upload INIT', [
            'total_bytes' => $totalBytes,
            'media_type' => $mimeType,
            'media_category' => $mediaCategory,
        ]);

        // INIT - Use dedicated initialize endpoint
        $initResponse = Http::withToken($this->accessToken)
            ->timeout(60)
            ->post("{$this->baseUrl}/2/media/upload/initialize", [
                'media_type' => $mimeType,
                'media_category' => $mediaCategory,
                'total_bytes' => $totalBytes,
            ]);

        if ($initResponse->failed()) {
            Log::error('X chunked upload INIT error', [
                'status' => $initResponse->status(),
                'body' => $initResponse->body(),
            ]);
            $this->handleApiError($initResponse, 'Failed to initialize chunked upload');
        }

        $initData = $initResponse->json();
        $mediaId = $initData['data']['id'] ?? $initData['media_id'] ?? null;

        if (! $mediaId) {
            throw new \Exception('No media_id returned from INIT');
        }

        Log::info('X chunked upload INIT success', ['media_id' => $mediaId]);

        // APPEND - Upload in 1MB chunks (API limit)
        $chunkSize = 1 * 1024 * 1024;
        $chunks = str_split($mediaContent, $chunkSize);

        foreach ($chunks as $index => $chunk) {
            Log::info('X chunked upload APPEND', [
                'media_id' => $mediaId,
                'segment' => $index,
                'chunk_size' => strlen($chunk),
            ]);

            // APPEND uses the new v2 endpoint with media_id in URL
            $appendResponse = Http::withToken($this->accessToken)
                ->timeout(300)
                ->attach('media', $chunk, 'chunk'.$index)
                ->post("{$this->baseUrl}/2/media/upload/{$mediaId}/append", [
                    'segment_index' => $index,
                ]);

            if ($appendResponse->failed()) {
                Log::error('X chunked upload APPEND error', [
                    'status' => $appendResponse->status(),
                    'body' => $appendResponse->body(),
                    'segment' => $index,
                ]);
                $this->handleApiError($appendResponse, 'Failed to append chunk');
            }
        }

        // FINALIZE - Use the new v2 endpoint
        Log::info('X chunked upload FINALIZE', ['media_id' => $mediaId]);

        $finalizeResponse = Http::withToken($this->accessToken)
            ->timeout(60)
            ->post("{$this->baseUrl}/2/media/upload/{$mediaId}/finalize");

        if ($finalizeResponse->failed()) {
            Log::error('X chunked upload FINALIZE error', [
                'status' => $finalizeResponse->status(),
                'body' => $finalizeResponse->body(),
            ]);
            $this->handleApiError($finalizeResponse, 'Failed to finalize chunked upload');
        }

        $finalizeData = $finalizeResponse->json();

        // Wait for processing (videos need transcoding)
        if (isset($finalizeData['processing_info']) || str_starts_with($mimeType, 'video/')) {
            $this->waitForProcessing($mediaId);
        }

        // Return in same format as simple upload
        return [
            'data' => [
                'id' => $mediaId,
            ],
        ];
    }

    private function getMediaCategory(string $mimeType, int $fileSize): ?string
    {
        if (str_starts_with($mimeType, 'video/')) {
            return $fileSize > 15 * 1024 * 1024 ? 'amplify_video' : 'tweet_video';
        }

        if ($mimeType === 'image/gif') {
            return 'tweet_gif';
        }

        if (str_starts_with($mimeType, 'image/')) {
            return 'tweet_image';
        }

        return null;
    }

    private function waitForProcessing(string $mediaId, int $maxAttempts = 20): bool
    {
        for ($i = 0; $i < $maxAttempts; $i++) {
            $response = $this->getHttpClient()
                ->get("{$this->baseUrl}/2/media/{$mediaId}");

            if ($response->failed()) {
                Log::error('X media status check error: '.$response->body());
                sleep(3);

                continue;
            }

            $responseData = $response->json();

            // If processing_info doesn't exist, assume it's ready
            if (! isset($responseData['processing_info'])) {
                return true;
            }

            $state = $responseData['processing_info']['state'] ?? 'unknown';

            if ($state === 'succeeded') {
                return true;
            }

            if ($state === 'failed') {
                $error = $responseData['processing_info']['error'] ?? 'Unknown error';
                Log::error('X media processing failed: '.$error);

                return false;
            }

            // Wait before checking again
            $waitTime = $responseData['processing_info']['check_after_secs'] ?? 3;
            sleep($waitTime);
        }

        return false;
    }

    private function refreshToken(SocialAccount $account): void
    {
        if (! $account->refresh_token) {
            throw new TokenExpiredException('No refresh token available for X account');
        }

        $response = Http::asForm()->post("{$this->baseUrl}/2/oauth2/token", [
            'grant_type' => 'refresh_token',
            'refresh_token' => $account->refresh_token,
            'client_id' => config('services.x.client_id'),
        ]);

        if ($response->failed()) {
            $this->handleApiError($response, 'Failed to refresh X token');
        }

        $data = $response->json();

        $account->update([
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? $account->refresh_token,
            'token_expires_at' => now()->addSeconds($data['expires_in'] ?? 7200),
        ]);
    }

    private function handleApiError(Response $response, string $context): void
    {
        $body = $response->json() ?? [];
        $errorTitle = $body['title'] ?? null;
        $message = $body['detail'] ?? $response->body();

        if ($response->status() === 401 || in_array($errorTitle, self::TOKEN_ERROR_TITLES)) {
            throw new TokenExpiredException(
                "{$context}: {$message}",
                $errorTitle
            );
        }

        throw new \Exception("{$context}: {$message}");
    }
}
