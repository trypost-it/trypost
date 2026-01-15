<?php

namespace App\Services\Social;

use App\Models\PostPlatform;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FacebookPublisher
{
    private string $baseUrl = 'https://graph.facebook.com/v21.0';

    public function publish(PostPlatform $postPlatform): array
    {
        $account = $postPlatform->socialAccount;
        $pageId = $account->platform_user_id;
        $accessToken = $account->access_token;

        $media = $postPlatform->media;

        // Text only post
        if ($media->isEmpty()) {
            return $this->publishTextPost($pageId, $accessToken, $postPlatform->content);
        }

        $firstMedia = $media->first();
        $isVideo = str_starts_with($firstMedia->mime_type, 'video/');
        $isImage = str_starts_with($firstMedia->mime_type, 'image/');

        if ($isVideo) {
            return $this->publishVideoPost($pageId, $accessToken, $postPlatform->content, $firstMedia);
        }

        if ($isImage) {
            // Single or multiple images
            if ($media->count() === 1) {
                return $this->publishSingleImagePost($pageId, $accessToken, $postPlatform->content, $firstMedia);
            }

            return $this->publishMultiImagePost($pageId, $accessToken, $postPlatform->content, $media);
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
            throw new \Exception('Facebook API error: '.$response->body());
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
            throw new \Exception('Facebook API error: '.$response->body());
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
            throw new \Exception('Facebook API error: '.$response->body());
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
            throw new \Exception('Facebook API error: '.$response->body());
        }

        $data = $response->json();
        $videoId = $data['id'];

        return [
            'id' => $videoId,
            'url' => "https://www.facebook.com/{$pageId}/videos/{$videoId}",
        ];
    }
}
