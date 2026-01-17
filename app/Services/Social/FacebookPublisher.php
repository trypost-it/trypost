<?php

namespace App\Services\Social;

use App\Enums\PostPlatform\ContentType;
use App\Exceptions\TokenExpiredException;
use App\Models\PostPlatform;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FacebookPublisher
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

    private string $baseUrl = 'https://graph.facebook.com/v21.0';

    public function publish(PostPlatform $postPlatform): array
    {
        $account = $postPlatform->socialAccount;
        $pageId = $account->platform_user_id;
        $accessToken = $account->access_token;

        $media = $postPlatform->media;
        $contentType = $postPlatform->content_type;

        return match ($contentType) {
            ContentType::FacebookReel => $this->publishReel($pageId, $accessToken, $postPlatform->content, $media->first()),
            ContentType::FacebookStory => $this->publishStory($pageId, $accessToken, $media->first()),
            ContentType::FacebookPost => $this->publishPost($pageId, $accessToken, $postPlatform->content, $media),
            default => throw new \Exception("Unsupported Facebook content type: {$contentType?->value}"),
        };
    }

    private function publishPost(string $pageId, string $accessToken, string $content, $media): array
    {
        // Text only post
        if ($media->isEmpty()) {
            return $this->publishTextPost($pageId, $accessToken, $content);
        }

        $firstMedia = $media->first();
        $isVideo = str_starts_with($firstMedia->mime_type, 'video/');
        $isImage = str_starts_with($firstMedia->mime_type, 'image/');

        if ($isVideo) {
            return $this->publishVideoPost($pageId, $accessToken, $content, $firstMedia);
        }

        if ($isImage) {
            // Single or multiple images
            if ($media->count() === 1) {
                return $this->publishSingleImagePost($pageId, $accessToken, $content, $firstMedia);
            }

            return $this->publishMultiImagePost($pageId, $accessToken, $content, $media);
        }

        throw new \Exception('Unsupported media type for Facebook');
    }

    private function publishTextPost(string $pageId, string $accessToken, string $content): array
    {
        Log::info('Facebook publishing text post', ['page_id' => $pageId]);

        $response = Http::post("{$this->baseUrl}/{$pageId}/feed", [
            'message' => $content,
            'access_token' => $accessToken,
        ]);

        if ($response->failed()) {
            Log::error('Facebook text post failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            $this->handleApiError($response, 'Facebook API error');
        }

        $data = $response->json();
        $postId = $data['id'];

        return [
            'id' => $postId,
            'url' => "https://www.facebook.com/{$postId}",
        ];
    }

    private function publishSingleImagePost(string $pageId, string $accessToken, string $content, $media): array
    {
        Log::info('Facebook publishing single image post', ['page_id' => $pageId]);

        $response = Http::post("{$this->baseUrl}/{$pageId}/photos", [
            'message' => $content,
            'url' => $media->url,
            'access_token' => $accessToken,
        ]);

        if ($response->failed()) {
            Log::error('Facebook single image post failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            $this->handleApiError($response, 'Facebook API error');
        }

        $data = $response->json();
        $postId = $data['post_id'] ?? $data['id'];

        return [
            'id' => $postId,
            'url' => "https://www.facebook.com/{$postId}",
        ];
    }

    private function publishMultiImagePost(string $pageId, string $accessToken, string $content, $mediaCollection): array
    {
        Log::info('Facebook publishing multi-image post', [
            'page_id' => $pageId,
            'image_count' => $mediaCollection->count(),
        ]);

        // Upload each image as unpublished
        $attachedMedia = [];

        foreach ($mediaCollection as $media) {
            if (! str_starts_with($media->mime_type, 'image/')) {
                continue;
            }

            $uploadResponse = Http::post("{$this->baseUrl}/{$pageId}/photos", [
                'url' => $media->url,
                'published' => 'false',
                'access_token' => $accessToken,
            ]);

            if ($uploadResponse->failed()) {
                Log::error('Facebook image upload failed', [
                    'body' => $uploadResponse->body(),
                ]);

                continue;
            }

            $uploadData = $uploadResponse->json();
            $attachedMedia[] = ['media_fbid' => $uploadData['id']];
        }

        if (empty($attachedMedia)) {
            throw new \Exception('Failed to upload any images to Facebook');
        }

        // Create the post with attached media
        $postData = [
            'message' => $content,
            'access_token' => $accessToken,
        ];

        foreach ($attachedMedia as $index => $media) {
            $postData["attached_media[{$index}]"] = json_encode($media);
        }

        $response = Http::post("{$this->baseUrl}/{$pageId}/feed", $postData);

        if ($response->failed()) {
            Log::error('Facebook multi-image post failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            $this->handleApiError($response, 'Facebook API error');
        }

        $data = $response->json();
        $postId = $data['id'];

        return [
            'id' => $postId,
            'url' => "https://www.facebook.com/{$postId}",
        ];
    }

    private function publishVideoPost(string $pageId, string $accessToken, string $content, $media): array
    {
        Log::info('Facebook publishing video post', ['page_id' => $pageId]);

        // Use resumable upload for videos
        $response = Http::post("{$this->baseUrl}/{$pageId}/videos", [
            'description' => $content,
            'file_url' => $media->url,
            'access_token' => $accessToken,
        ]);

        if ($response->failed()) {
            Log::error('Facebook video post failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            $this->handleApiError($response, 'Facebook API error');
        }

        $data = $response->json();
        $videoId = $data['id'];

        return [
            'id' => $videoId,
            'url' => "https://www.facebook.com/{$pageId}/videos/{$videoId}",
        ];
    }

    private function publishReel(string $pageId, string $accessToken, string $content, $media): array
    {
        Log::info('Facebook publishing reel', ['page_id' => $pageId]);

        // Upload video as reel
        $response = Http::post("{$this->baseUrl}/{$pageId}/video_reels", [
            'upload_phase' => 'start',
            'access_token' => $accessToken,
        ]);

        if ($response->failed()) {
            Log::error('Facebook reel upload start failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            $this->handleApiError($response, 'Facebook API error');
        }

        $data = $response->json();
        $videoId = $data['video_id'];

        // Upload the video file
        $uploadResponse = Http::post("{$this->baseUrl}/{$videoId}", [
            'upload_phase' => 'transfer',
            'video_file_chunk' => $media->url,
            'access_token' => $accessToken,
        ]);

        if ($uploadResponse->failed()) {
            Log::error('Facebook reel upload transfer failed', [
                'body' => $uploadResponse->body(),
            ]);
            $this->handleApiError($uploadResponse, 'Facebook API error');
        }

        // Finish and publish the reel
        $finishResponse = Http::post("{$this->baseUrl}/{$pageId}/video_reels", [
            'upload_phase' => 'finish',
            'video_id' => $videoId,
            'video_state' => 'PUBLISHED',
            'description' => $content,
            'access_token' => $accessToken,
        ]);

        if ($finishResponse->failed()) {
            Log::error('Facebook reel finish failed', [
                'body' => $finishResponse->body(),
            ]);
            $this->handleApiError($finishResponse, 'Facebook API error');
        }

        $finishData = $finishResponse->json();
        $reelId = $finishData['id'] ?? $videoId;

        return [
            'id' => $reelId,
            'url' => "https://www.facebook.com/reel/{$reelId}",
        ];
    }

    private function publishStory(string $pageId, string $accessToken, $media): array
    {
        Log::info('Facebook publishing story', ['page_id' => $pageId]);

        $isVideo = str_starts_with($media->mime_type, 'video/');

        if ($isVideo) {
            // Video story
            $response = Http::post("{$this->baseUrl}/{$pageId}/video_stories", [
                'upload_phase' => 'start',
                'access_token' => $accessToken,
            ]);

            if ($response->failed()) {
                $this->handleApiError($response, 'Facebook API error');
            }

            $videoId = $response->json()['video_id'];

            // Transfer the video
            Http::post("{$this->baseUrl}/{$videoId}", [
                'upload_phase' => 'transfer',
                'video_file_chunk' => $media->url,
                'access_token' => $accessToken,
            ]);

            // Finish the story
            $finishResponse = Http::post("{$this->baseUrl}/{$pageId}/video_stories", [
                'upload_phase' => 'finish',
                'video_id' => $videoId,
                'access_token' => $accessToken,
            ]);

            if ($finishResponse->failed()) {
                $this->handleApiError($finishResponse, 'Facebook API error');
            }

            $storyId = $finishResponse->json()['post_id'] ?? $videoId;

            return [
                'id' => $storyId,
                'url' => "https://www.facebook.com/stories/{$pageId}/{$storyId}",
            ];
        }

        // Image story
        $response = Http::post("{$this->baseUrl}/{$pageId}/photo_stories", [
            'photo_id' => $this->uploadUnpublishedPhoto($pageId, $accessToken, $media),
            'access_token' => $accessToken,
        ]);

        if ($response->failed()) {
            Log::error('Facebook photo story failed', [
                'body' => $response->body(),
            ]);
            $this->handleApiError($response, 'Facebook API error');
        }

        $storyId = $response->json()['post_id'] ?? $response->json()['id'];

        return [
            'id' => $storyId,
            'url' => "https://www.facebook.com/stories/{$pageId}/{$storyId}",
        ];
    }

    private function uploadUnpublishedPhoto(string $pageId, string $accessToken, $media): string
    {
        $response = Http::post("{$this->baseUrl}/{$pageId}/photos", [
            'url' => $media->url,
            'published' => 'false',
            'access_token' => $accessToken,
        ]);

        if ($response->failed()) {
            $this->handleApiError($response, 'Facebook API error');
        }

        return $response->json()['id'];
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
