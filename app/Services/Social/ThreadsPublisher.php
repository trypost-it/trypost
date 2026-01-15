<?php

namespace App\Services\Social;

use App\Models\PostPlatform;
use App\Models\SocialAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ThreadsPublisher
{
    private string $baseUrl = 'https://graph.threads.net/v1.0';

    public function publish(PostPlatform $postPlatform): array
    {
        $account = $postPlatform->socialAccount;

        if ($account->is_token_expired || $account->is_token_expiring_soon) {
            $this->refreshToken($account);
            $account->refresh();
        }

        $userId = $account->platform_user_id;
        $accessToken = $account->access_token;

        $media = $postPlatform->media;

        // Text only post
        if ($media->isEmpty()) {
            return $this->publishTextPost($userId, $accessToken, $postPlatform->content);
        }

        $firstMedia = $media->first();
        $isVideo = str_starts_with($firstMedia->mime_type, 'video/');

        // Single media
        if ($media->count() === 1) {
            if ($isVideo) {
                return $this->publishVideoPost($userId, $accessToken, $postPlatform->content, $firstMedia);
            }

            return $this->publishImagePost($userId, $accessToken, $postPlatform->content, $firstMedia);
        }

        // Multiple media - carousel
        return $this->publishCarousel($userId, $accessToken, $postPlatform->content, $media);
    }

    private function publishTextPost(string $userId, string $accessToken, string $content): array
    {
        Log::info('Threads publishing text post', ['user_id' => $userId]);

        // Step 1: Create container
        $containerResponse = Http::post("{$this->baseUrl}/{$userId}/threads", [
            'media_type' => 'TEXT',
            'text' => $content,
            'access_token' => $accessToken,
        ]);

        if ($containerResponse->failed()) {
            Log::error('Threads container creation failed', [
                'status' => $containerResponse->status(),
                'body' => $containerResponse->body(),
            ]);
            throw new \Exception('Threads API error: '.$containerResponse->body());
        }

        $containerId = $containerResponse->json()['id'];

        // Step 2: Publish
        return $this->publishContainer($userId, $accessToken, $containerId);
    }

    private function publishImagePost(string $userId, string $accessToken, string $content, $media): array
    {
        Log::info('Threads publishing image post', ['user_id' => $userId]);

        // Step 1: Create container
        $containerResponse = Http::post("{$this->baseUrl}/{$userId}/threads", [
            'media_type' => 'IMAGE',
            'image_url' => $media->url,
            'text' => $content,
            'access_token' => $accessToken,
        ]);

        if ($containerResponse->failed()) {
            Log::error('Threads image container creation failed', [
                'status' => $containerResponse->status(),
                'body' => $containerResponse->body(),
            ]);
            throw new \Exception('Threads API error: '.$containerResponse->body());
        }

        $containerId = $containerResponse->json()['id'];

        // Step 2: Publish
        return $this->publishContainer($userId, $accessToken, $containerId);
    }

    private function publishVideoPost(string $userId, string $accessToken, string $content, $media): array
    {
        Log::info('Threads publishing video post', ['user_id' => $userId]);

        // Step 1: Create container
        $containerResponse = Http::post("{$this->baseUrl}/{$userId}/threads", [
            'media_type' => 'VIDEO',
            'video_url' => $media->url,
            'text' => $content,
            'access_token' => $accessToken,
        ]);

        if ($containerResponse->failed()) {
            Log::error('Threads video container creation failed', [
                'status' => $containerResponse->status(),
                'body' => $containerResponse->body(),
            ]);
            throw new \Exception('Threads API error: '.$containerResponse->body());
        }

        $containerId = $containerResponse->json()['id'];

        // Wait for video processing
        $this->waitForMediaProcessing($containerId, $accessToken);

        // Step 2: Publish
        return $this->publishContainer($userId, $accessToken, $containerId);
    }

    private function publishCarousel(string $userId, string $accessToken, string $content, $mediaCollection): array
    {
        Log::info('Threads publishing carousel', [
            'user_id' => $userId,
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
                $params['media_type'] = 'VIDEO';
                $params['video_url'] = $media->url;
            } else {
                $params['media_type'] = 'IMAGE';
                $params['image_url'] = $media->url;
            }

            $containerResponse = Http::post("{$this->baseUrl}/{$userId}/threads", $params);

            if ($containerResponse->failed()) {
                Log::error('Threads carousel item creation failed', [
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
        $carouselResponse = Http::post("{$this->baseUrl}/{$userId}/threads", [
            'media_type' => 'CAROUSEL',
            'text' => $content,
            'children' => implode(',', $childContainers),
            'access_token' => $accessToken,
        ]);

        if ($carouselResponse->failed()) {
            Log::error('Threads carousel container creation failed', [
                'body' => $carouselResponse->body(),
            ]);
            throw new \Exception('Threads API error: '.$carouselResponse->body());
        }

        $carouselId = $carouselResponse->json()['id'];

        // Step 3: Publish carousel
        return $this->publishContainer($userId, $accessToken, $carouselId);
    }

    private function publishContainer(string $userId, string $accessToken, string $containerId): array
    {
        $publishResponse = Http::post("{$this->baseUrl}/{$userId}/threads_publish", [
            'creation_id' => $containerId,
            'access_token' => $accessToken,
        ]);

        if ($publishResponse->failed()) {
            Log::error('Threads publish failed', [
                'status' => $publishResponse->status(),
                'body' => $publishResponse->body(),
            ]);
            throw new \Exception('Threads publish error: '.$publishResponse->body());
        }

        $mediaId = $publishResponse->json()['id'];

        // Get permalink
        $permalinkResponse = Http::get("{$this->baseUrl}/{$mediaId}", [
            'fields' => 'permalink',
            'access_token' => $accessToken,
        ]);

        $permalink = $permalinkResponse->json()['permalink'] ?? null;

        Log::info('Threads publish success', ['media_id' => $mediaId, 'permalink' => $permalink]);

        return [
            'id' => $mediaId,
            'url' => $permalink,
        ];
    }

    private function waitForMediaProcessing(string $containerId, string $accessToken, int $maxAttempts = 30): void
    {
        for ($i = 0; $i < $maxAttempts; $i++) {
            $statusResponse = Http::get("{$this->baseUrl}/{$containerId}", [
                'fields' => 'status',
                'access_token' => $accessToken,
            ]);

            if ($statusResponse->failed()) {
                sleep(3);

                continue;
            }

            $status = $statusResponse->json()['status'] ?? 'UNKNOWN';

            Log::info('Threads media processing status', ['status' => $status, 'attempt' => $i]);

            if ($status === 'FINISHED') {
                return;
            }

            if ($status === 'ERROR') {
                throw new \Exception('Threads media processing failed');
            }

            sleep(3);
        }

        Log::warning('Threads media processing timeout, proceeding anyway');
    }

    private function refreshToken(SocialAccount $account): void
    {
        // Threads uses long-lived tokens that can be refreshed
        $response = Http::get('https://graph.threads.net/refresh_access_token', [
            'grant_type' => 'th_refresh_token',
            'access_token' => $account->access_token,
        ]);

        if ($response->failed()) {
            Log::error('Threads token refresh failed', ['body' => $response->body()]);
            throw new \Exception('Failed to refresh Threads token: '.$response->body());
        }

        $data = $response->json();

        $account->update([
            'access_token' => $data['access_token'],
            'token_expires_at' => isset($data['expires_in']) ? now()->addSeconds($data['expires_in']) : null,
        ]);

        Log::info('Threads token refreshed successfully');
    }
}
