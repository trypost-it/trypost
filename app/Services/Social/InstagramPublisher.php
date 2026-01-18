<?php

namespace App\Services\Social;

use App\Enums\PostPlatform\ContentType;
use App\Exceptions\TokenExpiredException;
use App\Models\PostPlatform;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InstagramPublisher
{
    /**
     * Meta Graph API error codes that indicate token issues.
     *
     * @see https://developers.facebook.com/docs/graph-api/guides/error-handling
     */
    private const TOKEN_ERROR_CODES = [
        190, // Invalid OAuth access token
    ];

    private const TOKEN_ERROR_SUBCODES = [
        458, // App not installed
        459, // User checkpointed
        460, // Password changed
        463, // Session expired
        464, // Unconfirmed user
        467, // Invalid access token
    ];

    private string $baseUrl = 'https://graph.instagram.com/v24.0';

    public function publish(PostPlatform $postPlatform): array
    {
        $account = $postPlatform->socialAccount;
        $instagramId = $account->platform_user_id;
        $accessToken = $account->access_token;

        $media = $postPlatform->media;

        if ($media->isEmpty()) {
            throw new \Exception('Instagram requires at least one image or video.');
        }

        $firstMedia = $media->first();
        $contentType = $postPlatform->content_type;

        return match ($contentType) {
            ContentType::InstagramReel => $this->publishReel($instagramId, $accessToken, $postPlatform->content, $firstMedia),
            ContentType::InstagramStory => $this->publishStory($instagramId, $accessToken, $firstMedia),
            ContentType::InstagramFeed => $media->count() > 1
                ? $this->publishCarousel($instagramId, $accessToken, $postPlatform->content, $media)
                : $this->publishSingleImage($instagramId, $accessToken, $postPlatform->content, $firstMedia),
            default => throw new \Exception("Unsupported Instagram content type: {$contentType?->value}"),
        };
    }

    private function publishSingleImage(string $instagramId, string $accessToken, string $content, $media): array
    {
        Log::info('Instagram publishing single image', ['instagram_id' => $instagramId, 'image_url' => $media->url]);

        // Step 1: Create container
        $containerResponse = Http::post("{$this->baseUrl}/{$instagramId}/media", [
            'image_url' => $media->url,
            'caption' => $content,
            'access_token' => $accessToken,
        ]);

        Log::info('Instagram container response', [
            'status' => $containerResponse->status(),
            'body' => $containerResponse->json(),
        ]);

        if ($containerResponse->failed()) {
            Log::error('Instagram container creation failed', [
                'status' => $containerResponse->status(),
                'body' => $containerResponse->body(),
            ]);
            $this->handleApiError($containerResponse, 'Instagram API error');
        }

        $containerId = $containerResponse->json()['id'] ?? null;

        if (! $containerId) {
            throw new \Exception('Instagram container creation failed: No container ID returned');
        }

        // Step 2: Wait for container to be ready
        $this->waitForMediaProcessing($containerId, $accessToken);

        // Step 3: Publish container
        return $this->publishContainer($instagramId, $accessToken, $containerId);
    }

    private function publishReel(string $instagramId, string $accessToken, string $content, $media): array
    {
        Log::info('Instagram publishing reel', ['instagram_id' => $instagramId]);

        // Step 1: Create container for video/reel
        $containerResponse = Http::post("{$this->baseUrl}/{$instagramId}/media", [
            'video_url' => $media->url,
            'caption' => $content,
            'media_type' => 'REELS',
            'access_token' => $accessToken,
        ]);

        if ($containerResponse->failed()) {
            Log::error('Instagram reel container creation failed', [
                'status' => $containerResponse->status(),
                'body' => $containerResponse->body(),
            ]);
            $this->handleApiError($containerResponse, 'Instagram API error');
        }

        $containerId = $containerResponse->json()['id'] ?? null;

        if (! $containerId) {
            throw new \Exception('Instagram reel container creation failed: No container ID returned');
        }

        // Wait for video processing
        $this->waitForMediaProcessing($containerId, $accessToken);

        // Step 2: Publish container
        return $this->publishContainer($instagramId, $accessToken, $containerId);
    }

    private function publishStory(string $instagramId, string $accessToken, $media): array
    {
        Log::info('Instagram publishing story', ['instagram_id' => $instagramId]);

        $isVideo = str_starts_with($media->mime_type, 'video/');

        $params = [
            'media_type' => 'STORIES',
            'access_token' => $accessToken,
        ];

        if ($isVideo) {
            $params['video_url'] = $media->url;
        } else {
            $params['image_url'] = $media->url;
        }

        // Step 1: Create story container
        $containerResponse = Http::post("{$this->baseUrl}/{$instagramId}/media", $params);

        if ($containerResponse->failed()) {
            Log::error('Instagram story container creation failed', [
                'status' => $containerResponse->status(),
                'body' => $containerResponse->body(),
            ]);
            $this->handleApiError($containerResponse, 'Instagram API error');
        }

        $containerId = $containerResponse->json()['id'] ?? null;

        if (! $containerId) {
            throw new \Exception('Instagram story container creation failed: No container ID returned');
        }

        // Step 2: Wait for media processing
        $this->waitForMediaProcessing($containerId, $accessToken);

        // Step 3: Publish story container
        return $this->publishContainer($instagramId, $accessToken, $containerId);
    }

    private function publishCarousel(string $instagramId, string $accessToken, string $content, $mediaCollection): array
    {
        Log::info('Instagram publishing carousel', [
            'instagram_id' => $instagramId,
            'media_count' => $mediaCollection->count(),
        ]);

        // Step 1: Create containers for each media item
        $childContainers = [];

        foreach ($mediaCollection as $media) {
            $isVideo = str_starts_with($media->mime_type, 'video/');

            $params = [
                'is_carousel_item' => 'true',
                'access_token' => $accessToken,
            ];

            if ($isVideo) {
                $params['video_url'] = $media->url;
                $params['media_type'] = 'VIDEO';
            } else {
                $params['image_url'] = $media->url;
            }

            $containerResponse = Http::post("{$this->baseUrl}/{$instagramId}/media", $params);

            if ($containerResponse->failed()) {
                Log::error('Instagram carousel item creation failed', [
                    'body' => $containerResponse->body(),
                ]);

                continue;
            }

            $childId = $containerResponse->json()['id'];

            // Wait for video processing if needed
            if ($isVideo) {
                $this->waitForMediaProcessing($childId, $accessToken);
            }

            $childContainers[] = $childId;
        }

        if (empty($childContainers)) {
            throw new \Exception('Failed to create any carousel items');
        }

        // Step 2: Create carousel container
        $carouselResponse = Http::post("{$this->baseUrl}/{$instagramId}/media", [
            'media_type' => 'CAROUSEL',
            'caption' => $content,
            'children' => implode(',', $childContainers),
            'access_token' => $accessToken,
        ]);

        if ($carouselResponse->failed()) {
            Log::error('Instagram carousel container creation failed', [
                'body' => $carouselResponse->body(),
            ]);
            $this->handleApiError($carouselResponse, 'Instagram API error');
        }

        $carouselId = $carouselResponse->json()['id'] ?? null;

        if (! $carouselId) {
            throw new \Exception('Instagram carousel container creation failed: No container ID returned');
        }

        // Step 3: Wait for carousel to be ready
        $this->waitForMediaProcessing($carouselId, $accessToken);

        // Step 4: Publish carousel
        return $this->publishContainer($instagramId, $accessToken, $carouselId);
    }

    private function publishContainer(string $instagramId, string $accessToken, string $containerId): array
    {
        $publishResponse = Http::post("{$this->baseUrl}/{$instagramId}/media_publish", [
            'creation_id' => $containerId,
            'access_token' => $accessToken,
        ]);

        if ($publishResponse->failed()) {
            Log::error('Instagram publish failed', [
                'status' => $publishResponse->status(),
                'body' => $publishResponse->body(),
            ]);
            $this->handleApiError($publishResponse, 'Instagram publish error');
        }

        $mediaId = $publishResponse->json()['id'];

        // Get permalink
        $permalinkResponse = Http::get("{$this->baseUrl}/{$mediaId}", [
            'fields' => 'permalink',
            'access_token' => $accessToken,
        ]);

        $permalink = $permalinkResponse->json()['permalink'] ?? null;

        Log::info('Instagram publish success', ['media_id' => $mediaId, 'permalink' => $permalink]);

        return [
            'id' => $mediaId,
            'url' => $permalink,
        ];
    }

    private function waitForMediaProcessing(string $containerId, string $accessToken, int $maxAttempts = 30): void
    {
        for ($i = 0; $i < $maxAttempts; $i++) {
            $statusResponse = Http::get("{$this->baseUrl}/{$containerId}", [
                'fields' => 'status_code',
                'access_token' => $accessToken,
            ]);

            if ($statusResponse->failed()) {
                sleep(5);

                continue;
            }

            $status = $statusResponse->json()['status_code'] ?? 'UNKNOWN';

            Log::info('Instagram media processing status', ['status' => $status, 'attempt' => $i]);

            if ($status === 'FINISHED') {
                return;
            }

            if ($status === 'ERROR') {
                throw new \Exception('Instagram media processing failed');
            }

            sleep(5);
        }

        Log::warning('Instagram media processing timeout, proceeding anyway');
    }

    private function handleApiError(Response $response, string $context): void
    {
        $body = $response->json() ?? [];
        $error = $body['error'] ?? [];
        $errorCode = $error['code'] ?? null;
        $errorSubcode = $error['error_subcode'] ?? null;
        $errorType = $error['type'] ?? null;
        $message = $error['message'] ?? $response->body();

        $isTokenError = $errorType === 'OAuthException'
            || in_array($errorCode, self::TOKEN_ERROR_CODES)
            || in_array($errorSubcode, self::TOKEN_ERROR_SUBCODES);

        if ($isTokenError) {
            throw new TokenExpiredException(
                "{$context}: {$message}",
                $errorCode ? (string) $errorCode : null
            );
        }

        throw new \Exception("{$context}: {$message}");
    }
}
