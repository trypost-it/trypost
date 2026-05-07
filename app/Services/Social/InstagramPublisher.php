<?php

declare(strict_types=1);

namespace App\Services\Social;

use App\Enums\PostPlatform\ContentType;
use App\Enums\SocialAccount\Platform;
use App\Exceptions\Social\ErrorCategory;
use App\Exceptions\Social\InstagramPublishException;
use App\Exceptions\TokenExpiredException;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Services\Media\MediaOptimizer;
use App\Services\Social\Concerns\HasSocialHttpClient;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class InstagramPublisher
{
    use HasSocialHttpClient;

    private string $baseUrl;

    public function publish(PostPlatform $postPlatform): array
    {
        $this->validateContentLength($postPlatform);

        $account = $postPlatform->socialAccount;
        $this->baseUrl = $account->platform->instagramGraphBaseUrl();

        if ($account->is_token_expired || $account->is_token_expiring_soon) {
            $this->refreshTokenWithLock($account, fn () => $this->refreshToken($account));
            $account->refresh();
        }

        $instagramId = $account->platform_user_id;
        $accessToken = $account->access_token;

        $content = $postPlatform->post->content ? app(ContentSanitizer::class)->sanitize($postPlatform->post->content, $postPlatform->platform) : null;

        $media = $postPlatform->post->mediaItems;

        if ($media->isEmpty()) {
            throw new InstagramPublishException(
                userMessage: 'Instagram requires at least one image or video.',
                category: ErrorCategory::MediaFormat,
            );
        }

        $firstMedia = $media->first();
        $contentType = $postPlatform->content_type;

        $aspectRatio = data_get($postPlatform->meta, 'aspect_ratio');

        return match ($contentType) {
            ContentType::InstagramReel => $this->publishReel($instagramId, $accessToken, $content, $firstMedia),
            ContentType::InstagramStory => $this->publishStory($instagramId, $accessToken, $firstMedia),
            ContentType::InstagramFeed => $this->publishFeed($instagramId, $accessToken, $content, $media, $aspectRatio),
            default => throw new InstagramPublishException(
                userMessage: "Unsupported Instagram content type: {$contentType?->value}",
                category: ErrorCategory::ContentPolicy,
            ),
        };
    }

    private function publishFeed(string $instagramId, string $accessToken, ?string $content, $media, ?string $aspectRatio): array
    {
        if ($media->count() > 1) {
            return $this->publishCarousel($instagramId, $accessToken, $content, $media, $aspectRatio);
        }

        $firstMedia = $media->first();

        if ($firstMedia->isVideo()) {
            return $this->publishReel($instagramId, $accessToken, $content, $firstMedia);
        }

        return $this->publishSingleImage($instagramId, $accessToken, $content, $firstMedia, $aspectRatio);
    }

    private function publishSingleImage(string $instagramId, string $accessToken, ?string $content, $media, ?string $aspectRatio): array
    {
        $imageUrl = $this->cropForFeed($media->url, $aspectRatio);

        // Step 1: Create container
        $containerResponse = $this->socialHttp()->post("{$this->baseUrl}/{$instagramId}/media", [
            'image_url' => $imageUrl,
            'caption' => $content,
            'access_token' => $accessToken,
        ]);

        if ($containerResponse->failed()) {
            Log::error('Instagram container creation failed', [
                'status' => $containerResponse->status(),
                'body' => $this->redactResponseBody($containerResponse->body()),
            ]);
            $this->handleApiError($containerResponse);
        }

        $containerId = $containerResponse->json()['id'] ?? null;

        if (! $containerId) {
            throw new InstagramPublishException(
                userMessage: 'Instagram container creation failed: No container ID returned',
                category: ErrorCategory::ServerError,
            );
        }

        // Step 2: Wait for container to be ready
        $this->waitForMediaProcessing($containerId, $accessToken);

        // Step 3: Publish container
        return $this->publishContainer($instagramId, $accessToken, $containerId);
    }

    private function publishReel(string $instagramId, string $accessToken, ?string $content, $media): array
    {
        // Step 1: Create container for video/reel
        $containerResponse = $this->socialHttp()->post("{$this->baseUrl}/{$instagramId}/media", [
            'video_url' => $media->url,
            'caption' => $content,
            'media_type' => 'REELS',
            'access_token' => $accessToken,
        ]);

        if ($containerResponse->failed()) {
            Log::error('Instagram reel container creation failed', [
                'status' => $containerResponse->status(),
                'body' => $this->redactResponseBody($containerResponse->body()),
            ]);
            $this->handleApiError($containerResponse);
        }

        $containerId = $containerResponse->json()['id'] ?? null;

        if (! $containerId) {
            throw new InstagramPublishException(
                userMessage: 'Instagram reel container creation failed: No container ID returned',
                category: ErrorCategory::ServerError,
            );
        }

        // Wait for video processing
        $this->waitForMediaProcessing($containerId, $accessToken);

        // Step 2: Publish container
        return $this->publishContainer($instagramId, $accessToken, $containerId);
    }

    private function publishStory(string $instagramId, string $accessToken, $media): array
    {
        $isVideo = $media->isVideo();

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
        $containerResponse = $this->socialHttp()->post("{$this->baseUrl}/{$instagramId}/media", $params);

        if ($containerResponse->failed()) {
            Log::error('Instagram story container creation failed', [
                'status' => $containerResponse->status(),
                'body' => $this->redactResponseBody($containerResponse->body()),
            ]);
            $this->handleApiError($containerResponse);
        }

        $containerId = $containerResponse->json()['id'] ?? null;

        if (! $containerId) {
            throw new InstagramPublishException(
                userMessage: 'Instagram story container creation failed: No container ID returned',
                category: ErrorCategory::ServerError,
            );
        }

        // Step 2: Wait for media processing
        $this->waitForMediaProcessing($containerId, $accessToken);

        // Step 3: Publish story container
        return $this->publishContainer($instagramId, $accessToken, $containerId);
    }

    private function publishCarousel(string $instagramId, string $accessToken, ?string $content, $mediaCollection, ?string $aspectRatio): array
    {
        // Step 1: Create containers for each media item
        $childContainers = [];

        foreach ($mediaCollection as $media) {
            $isVideo = $media->isVideo();

            $params = [
                'is_carousel_item' => 'true',
                'access_token' => $accessToken,
            ];

            if ($isVideo) {
                $params['video_url'] = $media->url;
                $params['media_type'] = 'VIDEO';
            } else {
                $params['image_url'] = $this->cropForFeed($media->url, $aspectRatio);
            }

            $containerResponse = $this->socialHttp()->post("{$this->baseUrl}/{$instagramId}/media", $params);

            if ($containerResponse->failed()) {
                Log::error('Instagram carousel item creation failed', [
                    'body' => $this->redactResponseBody($containerResponse->body()),
                ]);

                continue;
            }

            $childId = $containerResponse->json()['id'] ?? null;

            if (! $childId) {
                Log::error('Instagram carousel item creation returned no ID', ['body' => $this->redactResponseBody($containerResponse->body())]);

                continue;
            }

            // Wait for video processing if needed
            if ($isVideo) {
                $this->waitForMediaProcessing($childId, $accessToken);
            }

            $childContainers[] = $childId;
        }

        if (empty($childContainers)) {
            throw new InstagramPublishException(
                userMessage: 'Failed to create any carousel items',
                category: ErrorCategory::ServerError,
            );
        }

        // Step 2: Create carousel container
        $carouselResponse = $this->socialHttp()->post("{$this->baseUrl}/{$instagramId}/media", [
            'media_type' => 'CAROUSEL',
            'caption' => $content,
            'children' => implode(',', $childContainers),
            'access_token' => $accessToken,
        ]);

        if ($carouselResponse->failed()) {
            Log::error('Instagram carousel container creation failed', [
                'body' => $this->redactResponseBody($carouselResponse->body()),
            ]);
            $this->handleApiError($carouselResponse);
        }

        $carouselId = $carouselResponse->json()['id'] ?? null;

        if (! $carouselId) {
            throw new InstagramPublishException(
                userMessage: 'Instagram carousel container creation failed: No container ID returned',
                category: ErrorCategory::ServerError,
            );
        }

        // Step 3: Wait for carousel to be ready
        $this->waitForMediaProcessing($carouselId, $accessToken);

        // Step 4: Publish carousel
        return $this->publishContainer($instagramId, $accessToken, $carouselId);
    }

    private function publishContainer(string $instagramId, string $accessToken, string $containerId): array
    {
        $publishResponse = $this->socialHttp()->post("{$this->baseUrl}/{$instagramId}/media_publish", [
            'creation_id' => $containerId,
            'access_token' => $accessToken,
        ]);

        if ($publishResponse->failed()) {
            Log::error('Instagram publish failed', [
                'status' => $publishResponse->status(),
                'body' => $this->redactResponseBody($publishResponse->body()),
            ]);
            $this->handleApiError($publishResponse);
        }

        $mediaId = $publishResponse->json()['id'] ?? null;

        if (! $mediaId) {
            throw new InstagramPublishException(
                userMessage: 'Instagram publish failed: no media ID returned',
                category: ErrorCategory::ServerError,
            );
        }

        // Get permalink
        $permalinkResponse = $this->socialHttp()->get("{$this->baseUrl}/{$mediaId}", [
            'fields' => 'permalink',
            'access_token' => $accessToken,
        ]);

        $permalink = $permalinkResponse->json()['permalink'] ?? null;

        return [
            'id' => $mediaId,
            'url' => $permalink,
        ];
    }

    /**
     * Crop the image to the user-selected aspect ratio and return a public URL
     * Instagram can fetch. Returns the original URL untouched when no ratio is
     * set or 'original' is selected (caller has already validated the original
     * fits IG's 4:5 to 1.91:1 range).
     */
    private function cropForFeed(string $imageUrl, ?string $aspectRatio): string
    {
        if (! $aspectRatio || $aspectRatio === 'original') {
            return $imageUrl;
        }

        $ratio = $this->aspectRatioToFloat($aspectRatio);

        $tempInput = tempnam(sys_get_temp_dir(), 'ig_crop_in_');

        try {
            $download = Http::sink($tempInput)->timeout(120)->get($imageUrl);

            if ($download->failed()) {
                throw new InstagramPublishException(
                    userMessage: 'Failed to download image for cropping',
                    category: ErrorCategory::ServerError,
                );
            }

            $cropped = app(MediaOptimizer::class)->cropToAspectRatio($tempInput, $ratio);

            $path = 'instagram-crops/'.Str::uuid()->toString().'.jpg';
            Storage::put($path, file_get_contents($cropped));

            @unlink($cropped);

            return Storage::url($path);
        } finally {
            @unlink($tempInput);
        }
    }

    private function aspectRatioToFloat(string $ratio): float
    {
        return match ($ratio) {
            '4:5' => 4 / 5,
            '16:9' => 16 / 9,
            default => 1.0,
        };
    }

    private function waitForMediaProcessing(string $containerId, string $accessToken, int $maxAttempts = 30): void
    {
        for ($i = 0; $i < $maxAttempts; $i++) {
            $statusResponse = $this->socialHttp()->get("{$this->baseUrl}/{$containerId}", [
                'fields' => 'status_code',
                'access_token' => $accessToken,
            ]);

            if ($statusResponse->failed()) {
                sleep(5);

                continue;
            }

            $status = $statusResponse->json()['status_code'] ?? 'UNKNOWN';

            if ($status === 'FINISHED') {
                return;
            }

            if ($status === 'ERROR') {
                throw new InstagramPublishException(
                    userMessage: 'Instagram media processing failed',
                    category: ErrorCategory::ServerError,
                );
            }

            sleep(5);
        }

        Log::warning('Instagram media processing timeout, proceeding anyway');
    }

    private function refreshToken(SocialAccount $account): void
    {
        // Instagram via Facebook uses page tokens that don't expire
        if ($account->platform === Platform::InstagramFacebook) {
            return;
        }

        $response = Http::get(config('postpro.platforms.instagram.auth_api').'/refresh_access_token', [
            'grant_type' => 'ig_refresh_token',
            'access_token' => $account->access_token,
        ]);

        if ($response->failed()) {
            Log::error('Instagram token refresh failed', ['body' => $this->redactResponseBody($response->body())]);

            throw new TokenExpiredException('Failed to refresh Instagram token');
        }

        $data = $response->json();
        $newToken = data_get($data, 'access_token');

        $account->update([
            'access_token' => $newToken,
            'refresh_token' => $newToken,
            'token_expires_at' => data_get($data, 'expires_in') ? now()->addSeconds(data_get($data, 'expires_in')) : null,
        ]);

    }

    private function handleApiError(Response $response): never
    {
        throw InstagramPublishException::fromApiResponse($response);
    }
}

