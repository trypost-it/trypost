<?php

declare(strict_types=1);

namespace App\Services\Social;

use App\Enums\SocialAccount\Platform;
use App\Exceptions\Social\XPublishException;
use App\Exceptions\TokenExpiredException;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Services\Media\MediaOptimizer;
use App\Services\Social\Concerns\HasSocialHttpClient;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class XPublisher
{
    use HasSocialHttpClient;

    private string $baseUrl = 'https://api.x.com';

    private string $accessToken;

    public function publish(PostPlatform $postPlatform): array
    {
        $this->validateContentLength($postPlatform);

        $content = $postPlatform->content ? app(ContentSanitizer::class)->sanitize($postPlatform->content, $postPlatform->platform) : null;

        $account = $postPlatform->socialAccount;

        // Refresh token if expired or expiring soon
        if ($account->is_token_expired || $account->is_token_expiring_soon) {
            $this->refreshToken($account);
            $account->refresh();
        }

        $this->accessToken = $account->access_token;

        $data = [];

        if (! empty($content)) {
            $data['text'] = $content;
        }

        $mediaIds = [];
        $media = $postPlatform->media;

        if ($media->isNotEmpty()) {
            foreach ($media as $mediaItem) {
                $uploadedMedia = $this->uploadMedia($mediaItem);

                // v2 API returns data.id, v1 returns media_id
                $mediaId = data_get($uploadedMedia, 'data.id', data_get($uploadedMedia, 'media_id'));
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

        if (empty($content) && empty($mediaIds)) {
            throw new \Exception('X posts require either text or media. Please add content to your post.');
        }

        $response = $this->getHttpClient()
            ->post("{$this->baseUrl}/2/tweets", $data);

        if ($response->failed()) {
            Log::error('X post creation failed', [
                'status' => $response->status(),
                'body' => $this->redactResponseBody($response->body()),
            ]);
            $this->handleApiError($response);
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
        return $this->socialHttp()->withToken($this->accessToken)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ]);
    }

    private function uploadMedia($mediaItem): ?array
    {
        $mimeType = $mediaItem->mime_type;

        // Download to temp file (memory-safe)
        $tempFile = tempnam(sys_get_temp_dir(), 'x_media_');

        try {
            $downloadResponse = Http::withOptions(['sink' => $tempFile])->timeout(600)->get($mediaItem->url);

            if ($downloadResponse->failed()) {
                throw new \Exception('Failed to download media: HTTP '.$downloadResponse->status());
            }

            // Optimize images (skip GIFs — they need special handling)
            if (str_starts_with($mimeType, 'image/') && ! str_starts_with($mimeType, 'image/gif')) {
                $optimizer = app(MediaOptimizer::class);
                $optimizedPath = $optimizer->optimizeImage($tempFile, Platform::X);
                @unlink($tempFile);
                $tempFile = $optimizedPath;
                $mimeType = 'image/jpeg';
            }

            $fileSize = filesize($tempFile);
            $mediaCategory = $this->getMediaCategory($mimeType, $fileSize);

            $isVideo = str_starts_with($mimeType, 'video/');
            $isGif = $mimeType === 'image/gif';

            $useChunkedUpload = $isVideo || $isGif || $fileSize > 5 * 1024 * 1024;

            if ($useChunkedUpload) {
                return $this->chunkedUpload($tempFile, $fileSize, $mimeType, $mediaCategory);
            }

            // Simple upload for small images
            $response = $this->socialHttp()->withToken($this->accessToken)
                ->timeout(360)
                ->attach(
                    'media',
                    fopen($tempFile, 'r'),
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
                    'body' => $this->redactResponseBody($response->body()),
                ]);
                $this->handleApiError($response);
            }

            $responseData = $response->json();

            $mediaId = $responseData['data']['id'] ?? $responseData['media_id'] ?? null;

            if ($isGif && $mediaId) {
                $this->waitForProcessing($mediaId);
            }

            return $responseData;
        } finally {
            @unlink($tempFile);
        }
    }

    private function chunkedUpload(string $tempFile, int $totalBytes, string $mimeType, string $mediaCategory): array
    {
        // INIT
        $initResponse = $this->socialHttp()->withToken($this->accessToken)
            ->timeout(60)
            ->post("{$this->baseUrl}/2/media/upload/initialize", [
                'media_type' => $mimeType,
                'media_category' => $mediaCategory,
                'total_bytes' => $totalBytes,
            ]);

        if ($initResponse->failed()) {
            Log::error('X chunked upload INIT error', [
                'status' => $initResponse->status(),
                'body' => $this->redactResponseBody($initResponse->body()),
            ]);
            $this->handleApiError($initResponse);
        }

        $initData = $initResponse->json();
        $mediaId = $initData['data']['id'] ?? $initData['media_id'] ?? null;

        if (! $mediaId) {
            throw new \Exception('No media_id returned from INIT');
        }

        // APPEND - Read from temp file in 5MB chunks (memory-safe)
        $chunkSize = 5 * 1024 * 1024;
        $handle = fopen($tempFile, 'r');
        $index = 0;

        try {
            while (! feof($handle)) {
                $chunk = fread($handle, $chunkSize);

                if ($chunk === '' || $chunk === false) {
                    break;
                }

                $appendResponse = $this->socialHttp()->withToken($this->accessToken)
                    ->timeout(300)
                    ->attach('media', $chunk, 'chunk'.$index)
                    ->post("{$this->baseUrl}/2/media/upload/{$mediaId}/append", [
                        'segment_index' => $index,
                    ]);

                if ($appendResponse->failed()) {
                    Log::error('X chunked upload APPEND error', [
                        'status' => $appendResponse->status(),
                        'body' => $this->redactResponseBody($appendResponse->body()),
                        'segment' => $index,
                    ]);
                    $this->handleApiError($appendResponse);
                }

                $index++;
            }
        } finally {
            fclose($handle);
        }

        // FINALIZE - Use the new v2 endpoint
        $finalizeResponse = $this->socialHttp()->withToken($this->accessToken)
            ->timeout(60)
            ->post("{$this->baseUrl}/2/media/upload/{$mediaId}/finalize");

        if ($finalizeResponse->failed()) {
            Log::error('X chunked upload FINALIZE error', [
                'status' => $finalizeResponse->status(),
                'body' => $this->redactResponseBody($finalizeResponse->body()),
            ]);
            $this->handleApiError($finalizeResponse);
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
                Log::error('X media status check error', ['body' => $this->redactResponseBody($response->body())]);
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

        $response = Http::asForm()
            ->withBasicAuth(config('services.x.client_id'), config('services.x.client_secret'))
            ->post("{$this->baseUrl}/2/oauth2/token", [
                'grant_type' => 'refresh_token',
                'refresh_token' => $account->refresh_token,
            ]);

        if ($response->failed()) {
            $this->handleApiError($response);
        }

        $data = $response->json();

        $account->update([
            'access_token' => data_get($data, 'access_token'),
            'refresh_token' => data_get($data, 'refresh_token', $account->refresh_token),
            'token_expires_at' => now()->addSeconds(data_get($data, 'expires_in', 7200)),
        ]);
    }

    private function handleApiError(Response $response): never
    {
        throw XPublishException::fromApiResponse($response);
    }
}
