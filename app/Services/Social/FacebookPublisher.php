<?php

declare(strict_types=1);

namespace App\Services\Social;

use App\Enums\PostPlatform\ContentType;
use App\Exceptions\Social\FacebookPublishException;
use App\Models\PostPlatform;
use App\Services\Social\Concerns\HasSocialHttpClient;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FacebookPublisher
{
    use HasSocialHttpClient;

    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('trypost.platforms.facebook.graph_api');
    }

    public function publish(PostPlatform $postPlatform): array
    {
        $this->validateContentLength($postPlatform);

        $content = $postPlatform->post->content ? app(ContentSanitizer::class)->sanitize($postPlatform->post->content, $postPlatform->platform) : null;

        $account = $postPlatform->socialAccount;
        $pageId = $account->platform_user_id;
        $accessToken = $account->access_token;

        $media = $postPlatform->post->mediaItems;
        $contentType = $postPlatform->content_type;

        return match ($contentType) {
            ContentType::FacebookReel => $this->publishReel($pageId, $accessToken, $content, $media->first()),
            ContentType::FacebookStory => $this->publishStory($pageId, $accessToken, $media->first()),
            ContentType::FacebookPost => $this->publishPost($pageId, $accessToken, $content, $media),
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
        $response = $this->socialHttp()->post("{$this->baseUrl}/{$pageId}/feed", [
            'message' => $content,
            'access_token' => $accessToken,
        ]);

        if ($response->failed()) {
            Log::error('Facebook text post failed', [
                'status' => $response->status(),
                'body' => $this->redactResponseBody($response->body()),
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
        $response = $this->socialHttp()->post("{$this->baseUrl}/{$pageId}/photos", [
            'message' => $content,
            'url' => $media->url,
            'access_token' => $accessToken,
        ]);

        if ($response->failed()) {
            Log::error('Facebook single image post failed', [
                'status' => $response->status(),
                'body' => $this->redactResponseBody($response->body()),
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

            $uploadResponse = $this->socialHttp()->post("{$this->baseUrl}/{$pageId}/photos", [
                'url' => $media->url,
                'published' => 'false',
                'access_token' => $accessToken,
            ]);

            if ($uploadResponse->failed()) {
                Log::error('Facebook image upload failed', [
                    'body' => $this->redactResponseBody($uploadResponse->body()),
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

        $response = $this->socialHttp()->post("{$this->baseUrl}/{$pageId}/feed", $postData);

        if ($response->failed()) {
            Log::error('Facebook multi-image post failed', [
                'status' => $response->status(),
                'body' => $this->redactResponseBody($response->body()),
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
        $response = $this->socialHttp()->post("{$this->baseUrl}/{$pageId}/videos", [
            'description' => $content,
            'file_url' => $media->url,
            'access_token' => $accessToken,
        ]);

        if ($response->failed()) {
            Log::error('Facebook video post failed', [
                'status' => $response->status(),
                'body' => $this->redactResponseBody($response->body()),
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
        // Phase 1 (start) — graph endpoint returns video_id + upload_url.
        $startResponse = $this->socialHttp()->post("{$this->baseUrl}/{$pageId}/video_reels", [
            'upload_phase' => 'start',
            'access_token' => $accessToken,
        ]);

        if ($startResponse->failed()) {
            Log::error('Facebook reel upload start failed', [
                'status' => $startResponse->status(),
                'body' => $this->redactResponseBody($startResponse->body()),
            ]);
            $this->handleApiError($startResponse);
        }

        $startData = $startResponse->json();
        $videoId = data_get($startData, 'video_id');
        $uploadUrl = data_get($startData, 'upload_url');

        if (! $videoId || ! $uploadUrl) {
            Log::error('Facebook reel start did not return video_id/upload_url', [
                'body' => $this->redactResponseBody($startResponse->body()),
            ]);
            $this->handleApiError($startResponse);
        }

        // Phase 2 (transfer, local-file flow) — download our hosted
        // media then POST raw bytes to upload_url with the Offset and
        // file_size headers Facebook requires (the docs describe a
        // hosted-file shortcut with `file_url` in the body, but rupload
        // rejects it with "Header Offset not convertable to unsigned
        // long" — the headers are required either way).
        $tempFile = tempnam(sys_get_temp_dir(), 'fb_reel_');

        try {
            $download = Http::withOptions(['sink' => $tempFile])
                ->timeout(600)
                ->get($media->url);

            if ($download->failed()) {
                throw new \Exception('Failed to download reel media: HTTP '.$download->status());
            }

            $fileSize = filesize($tempFile);

            $uploadResponse = Http::withHeaders([
                'Authorization' => "OAuth {$accessToken}",
                'Offset' => '0',
                'file_size' => (string) $fileSize,
            ])
                ->timeout(600)
                ->withBody(file_get_contents($tempFile), $media->mime_type ?? 'video/mp4')
                ->post($uploadUrl);

            if ($uploadResponse->failed()) {
                Log::error('Facebook reel upload transfer failed', [
                    'status' => $uploadResponse->status(),
                    'body' => $this->redactResponseBody($uploadResponse->body()),
                ]);
                $this->handleApiError($uploadResponse);
            }
        } finally {
            @unlink($tempFile);
        }

        // Phase 3 (finish) — publish the reel.
        $finishResponse = $this->socialHttp()->post("{$this->baseUrl}/{$pageId}/video_reels", [
            'upload_phase' => 'finish',
            'video_id' => $videoId,
            'video_state' => 'PUBLISHED',
            'description' => $content,
            'access_token' => $accessToken,
        ]);

        if ($finishResponse->failed()) {
            Log::error('Facebook reel finish failed', [
                'body' => $this->redactResponseBody($finishResponse->body()),
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
            $response = $this->socialHttp()->post("{$this->baseUrl}/{$pageId}/video_stories", [
                'upload_phase' => 'start',
                'access_token' => $accessToken,
            ]);

            if ($response->failed()) {
                $this->handleApiError($response);
            }

            $videoId = $response->json()['video_id'] ?? null;

            if (! $videoId) {
                throw new \Exception('Facebook story upload failed: no video ID returned');
            }

            // Transfer the video (Facebook accepts URL in video_file_chunk)
            $transferResponse = $this->socialHttp()->post("{$this->baseUrl}/{$videoId}", [
                'upload_phase' => 'transfer',
                'video_file_chunk' => $media->url,
                'access_token' => $accessToken,
            ]);

            if ($transferResponse->failed()) {
                Log::error('Facebook video story transfer failed', ['body' => $this->redactResponseBody($transferResponse->body())]);
                $this->handleApiError($transferResponse);
            }

            // Finish the story
            $finishResponse = $this->socialHttp()->post("{$this->baseUrl}/{$pageId}/video_stories", [
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
        $response = $this->socialHttp()->post("{$this->baseUrl}/{$pageId}/photo_stories", [
            'photo_id' => $this->uploadUnpublishedPhoto($pageId, $accessToken, $media),
            'access_token' => $accessToken,
        ]);

        if ($response->failed()) {
            Log::error('Facebook photo story failed', [
                'body' => $this->redactResponseBody($response->body()),
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
        $response = $this->socialHttp()->post("{$this->baseUrl}/{$pageId}/photos", [
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
