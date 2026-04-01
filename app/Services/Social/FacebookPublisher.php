<?php

declare(strict_types=1);

namespace App\Services\Social;

use App\Enums\PostPlatform\ContentType;
use App\Exceptions\Social\FacebookPublishException;
use App\Models\PostPlatform;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FacebookPublisher
{
    private string $baseUrl = 'https://graph.facebook.com/v24.0';

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

    private function publishPost(string $pageId, string $accessToken, ?string $content, $media): array
    {
        // Text only post
        if ($media->isEmpty()) {
            if (empty($content)) {
                throw new \Exception('Facebook text posts require content. Please add text to your post.');
            }

            return $this->publishTextPost($pageId, $accessToken, $content);
        }

        $firstMedia = $media->first();
        $isVideo = $firstMedia->isVideo();
        $isImage = $firstMedia->isImage();

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
        $response = Http::post("{$this->baseUrl}/{$pageId}/feed", [
            'message' => $content,
            'access_token' => $accessToken,
        ]);

        if ($response->failed()) {
            Log::error('Facebook text post failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            $this->handleApiError($response);
        }

        $data = $response->json();
        $postId = data_get($data, 'id');

        return [
            'id' => $postId,
            'url' => "https://www.facebook.com/{$postId}",
        ];
    }

    private function publishSingleImagePost(string $pageId, string $accessToken, ?string $content, $media): array
    {
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
            $this->handleApiError($response);
        }

        $data = $response->json();
        $postId = data_get($data, 'post_id', data_get($data, 'id'));

        return [
            'id' => $postId,
            'url' => "https://www.facebook.com/{$postId}",
        ];
    }

    private function publishMultiImagePost(string $pageId, string $accessToken, ?string $content, $mediaCollection): array
    {
        // Upload each image as unpublished
        $attachedMedia = [];

        foreach ($mediaCollection as $media) {
            if (! $media->isImage()) {
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
            $this->handleApiError($response);
        }

        $data = $response->json();
        $postId = data_get($data, 'id');

        return [
            'id' => $postId,
            'url' => "https://www.facebook.com/{$postId}",
        ];
    }

    private function publishVideoPost(string $pageId, string $accessToken, ?string $content, $media): array
    {
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
            $this->handleApiError($response);
        }

        $data = $response->json();
        $videoId = data_get($data, 'id');

        return [
            'id' => $videoId,
            'url' => "https://www.facebook.com/{$pageId}/videos/{$videoId}",
        ];
    }

    private function publishReel(string $pageId, string $accessToken, ?string $content, $media): array
    {
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
            $this->handleApiError($response);
        }

        $data = $response->json();
        $videoId = data_get($data, 'video_id');

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
            $this->handleApiError($uploadResponse);
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
            $this->handleApiError($finishResponse);
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
        $isVideo = $media->isVideo();

        if ($isVideo) {
            // Video story
            $response = Http::post("{$this->baseUrl}/{$pageId}/video_stories", [
                'upload_phase' => 'start',
                'access_token' => $accessToken,
            ]);

            if ($response->failed()) {
                $this->handleApiError($response);
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
                $this->handleApiError($finishResponse);
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
            $this->handleApiError($response);
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
            $this->handleApiError($response);
        }

        return $response->json()['id'];
    }

    private function handleApiError(Response $response): never
    {
        throw FacebookPublishException::fromApiResponse($response);
    }
}
