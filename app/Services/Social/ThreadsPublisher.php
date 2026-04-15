<?php

declare(strict_types=1);

namespace App\Services\Social;

use App\Exceptions\Social\ThreadsPublishException;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Services\Social\Concerns\HasSocialHttpClient;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ThreadsPublisher
{
    use HasSocialHttpClient;

    private string $baseUrl = 'https://graph.threads.net/v1.0';

    public function publish(PostPlatform $postPlatform): array
    {
        $this->validateContentLength($postPlatform);

        $content = $postPlatform->post->content ? app(ContentSanitizer::class)->sanitize($postPlatform->post->content, $postPlatform->platform) : null;

        $account = $postPlatform->socialAccount;

        if ($account->is_token_expired || $account->is_token_expiring_soon) {
            $this->refreshTokenWithLock($account, fn () => $this->refreshToken($account));
            $account->refresh();
        }

        $userId = $account->platform_user_id;
        $accessToken = $account->access_token;

        $media = $postPlatform->post->mediaItems;

        // Text only post
        if ($media->isEmpty()) {
            if (empty($content)) {
                throw new \Exception('Threads text posts require content. Please add text to your post.');
            }

            return $this->publishTextPost($userId, $accessToken, $content);
        }

        $firstMedia = $media->first();
        $isVideo = $firstMedia->isVideo();

        // Single media
        if ($media->count() === 1) {
            if ($isVideo) {
                return $this->publishVideoPost($userId, $accessToken, $content, $firstMedia);
            }

            return $this->publishImagePost($userId, $accessToken, $content, $firstMedia);
        }

        // Multiple media - carousel
        return $this->publishCarousel($userId, $accessToken, $content, $media);
    }

    private function publishTextPost(string $userId, string $accessToken, string $content): array
    {
        // Step 1: Create container
        $containerResponse = $this->socialHttp()->post("{$this->baseUrl}/{$userId}/threads", [
            'media_type' => 'TEXT',
            'text' => $content,
            'access_token' => $accessToken,
        ]);

        if ($containerResponse->failed()) {
            Log::error('Threads container creation failed', [
                'status' => $containerResponse->status(),
                'body' => $this->redactResponseBody($containerResponse->body()),
            ]);
            $this->handleApiError($containerResponse);
        }

        $containerId = $containerResponse->json()['id'] ?? null;

        if (! $containerId) {
            throw new \Exception('Threads text container creation failed: no container ID returned');
        }

        // Step 2: Publish
        return $this->publishContainer($userId, $accessToken, $containerId);
    }

    private function publishImagePost(string $userId, string $accessToken, ?string $content, $media): array
    {
        // Step 1: Create container
        $containerResponse = $this->socialHttp()->post("{$this->baseUrl}/{$userId}/threads", [
            'media_type' => 'IMAGE',
            'image_url' => $media->url,
            'text' => $content,
            'access_token' => $accessToken,
        ]);

        if ($containerResponse->failed()) {
            Log::error('Threads image container creation failed', [
                'status' => $containerResponse->status(),
                'body' => $this->redactResponseBody($containerResponse->body()),
            ]);
            $this->handleApiError($containerResponse);
        }

        $containerId = $containerResponse->json()['id'] ?? null;

        if (! $containerId) {
            throw new \Exception('Threads image container creation failed: no container ID returned');
        }

        // Step 2: Wait for image processing
        $this->waitForMediaProcessing($containerId, $accessToken);

        // Step 3: Publish
        return $this->publishContainer($userId, $accessToken, $containerId);
    }

    private function publishVideoPost(string $userId, string $accessToken, ?string $content, $media): array
    {
        // Step 1: Create container
        $containerResponse = $this->socialHttp()->post("{$this->baseUrl}/{$userId}/threads", [
            'media_type' => 'VIDEO',
            'video_url' => $media->url,
            'text' => $content,
            'access_token' => $accessToken,
        ]);

        if ($containerResponse->failed()) {
            Log::error('Threads video container creation failed', [
                'status' => $containerResponse->status(),
                'body' => $this->redactResponseBody($containerResponse->body()),
            ]);
            $this->handleApiError($containerResponse);
        }

        $containerId = $containerResponse->json()['id'] ?? null;

        if (! $containerId) {
            throw new \Exception('Threads video container creation failed: no container ID returned');
        }

        // Wait for video processing
        $this->waitForMediaProcessing($containerId, $accessToken);

        // Step 2: Publish
        return $this->publishContainer($userId, $accessToken, $containerId);
    }

    private function publishCarousel(string $userId, string $accessToken, ?string $content, $mediaCollection): array
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
                $params['media_type'] = 'VIDEO';
                $params['video_url'] = $media->url;
            } else {
                $params['media_type'] = 'IMAGE';
                $params['image_url'] = $media->url;
            }

            $containerResponse = $this->socialHttp()->post("{$this->baseUrl}/{$userId}/threads", $params);

            if ($containerResponse->failed()) {
                Log::error('Threads carousel item creation failed', [
                    'body' => $this->redactResponseBody($containerResponse->body()),
                ]);

                continue;
            }

            $childId = $containerResponse->json()['id'] ?? null;

            if (! $childId) {
                Log::error('Threads carousel item creation returned no ID', ['body' => $this->redactResponseBody($containerResponse->body())]);

                continue;
            }

            // Wait for media processing (both images and videos)
            $this->waitForMediaProcessing($childId, $accessToken);

            $childContainers[] = $childId;
        }

        if (empty($childContainers)) {
            throw new \Exception('Failed to create any carousel items');
        }

        // Step 2: Create carousel container
        $carouselResponse = $this->socialHttp()->post("{$this->baseUrl}/{$userId}/threads", [
            'media_type' => 'CAROUSEL',
            'text' => $content,
            'children' => implode(',', $childContainers),
            'access_token' => $accessToken,
        ]);

        if ($carouselResponse->failed()) {
            Log::error('Threads carousel container creation failed', [
                'body' => $this->redactResponseBody($carouselResponse->body()),
            ]);
            $this->handleApiError($carouselResponse);
        }

        $carouselId = $carouselResponse->json()['id'] ?? null;

        if (! $carouselId) {
            throw new \Exception('Threads carousel container creation failed: no container ID returned');
        }

        // Step 3: Publish carousel
        return $this->publishContainer($userId, $accessToken, $carouselId);
    }

    private function publishContainer(string $userId, string $accessToken, string $containerId): array
    {
        $publishResponse = $this->socialHttp()->post("{$this->baseUrl}/{$userId}/threads_publish", [
            'creation_id' => $containerId,
            'access_token' => $accessToken,
        ]);

        if ($publishResponse->failed()) {
            Log::error('Threads publish failed', [
                'status' => $publishResponse->status(),
                'body' => $this->redactResponseBody($publishResponse->body()),
            ]);
            $this->handleApiError($publishResponse);
        }

        $mediaId = $publishResponse->json()['id'] ?? null;

        if (! $mediaId) {
            throw new \Exception('Threads publish failed: no media ID returned');
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

    private function waitForMediaProcessing(string $containerId, string $accessToken, int $maxAttempts = 30): void
    {
        for ($i = 0; $i < $maxAttempts; $i++) {
            $statusResponse = $this->socialHttp()->get("{$this->baseUrl}/{$containerId}", [
                'fields' => 'status,error_message',
                'access_token' => $accessToken,
            ]);

            if ($statusResponse->failed()) {
                Log::warning('Threads status check failed', [
                    'container_id' => $containerId,
                    'attempt' => $i,
                    'body' => $this->redactResponseBody($statusResponse->body()),
                ]);
                sleep(3);

                continue;
            }

            $data = $statusResponse->json();
            $status = data_get($data, 'status', 'UNKNOWN');

            if ($status === 'FINISHED') {
                return;
            }

            if ($status === 'ERROR') {
                $errorMessage = data_get($data, 'error_message', 'Unknown error');
                throw new \Exception('Threads media processing failed: '.$errorMessage);
            }

            sleep(3);
        }

        Log::warning('Threads media processing timeout', ['container_id' => $containerId]);
        throw new \Exception('Threads media processing timeout after '.$maxAttempts.' attempts');
    }

    private function refreshToken(SocialAccount $account): void
    {
        // Threads uses long-lived tokens that can be refreshed
        $response = Http::get('https://graph.threads.net/refresh_access_token', [
            'grant_type' => 'th_refresh_token',
            'access_token' => $account->access_token,
        ]);

        if ($response->failed()) {
            Log::error('Threads token refresh failed', ['body' => $this->redactResponseBody($response->body())]);
            $this->handleApiError($response);
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
        throw ThreadsPublishException::fromApiResponse($response);
    }
}
