<?php

namespace App\Services\Social;

use App\Models\PostPlatform;
use App\Models\SocialAccount;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class YouTubePublisher
{
    private string $baseUrl = 'https://www.googleapis.com';

    private string $accessToken;

    public function publish(PostPlatform $postPlatform): array
    {
        $account = $postPlatform->socialAccount;

        if ($account->is_token_expired || $account->is_token_expiring_soon) {
            $this->refreshToken($account);
            $account->refresh();
        }

        $this->accessToken = $account->access_token;

        $media = $postPlatform->media;

        if ($media->isEmpty()) {
            throw new \Exception('YouTube Shorts requires a video to publish.');
        }

        $firstMedia = $media->first();
        $isVideo = str_starts_with($firstMedia->mime_type, 'video/');

        if (! $isVideo) {
            throw new \Exception('YouTube Shorts only supports video content.');
        }

        return $this->publishShort($postPlatform, $firstMedia);
    }

    private function getHttpClient(): PendingRequest
    {
        return Http::withToken($this->accessToken)
            ->timeout(600);
    }

    private function publishShort(PostPlatform $postPlatform, $media): array
    {
        $title = $this->buildTitle($postPlatform->content);
        $description = $postPlatform->content;

        Log::info('YouTube Shorts publishing video', [
            'video_url' => $media->url,
            'title' => $title,
        ]);

        // Step 1: Get video content
        $videoContent = file_get_contents($media->url);
        $fileSize = strlen($videoContent);

        Log::info('YouTube video file size', ['size' => $fileSize]);

        // Step 2: Initialize resumable upload
        $initResponse = $this->getHttpClient()
            ->withHeaders([
                'Content-Type' => 'application/json; charset=UTF-8',
                'X-Upload-Content-Length' => $fileSize,
                'X-Upload-Content-Type' => $media->mime_type,
            ])
            ->post("{$this->baseUrl}/upload/youtube/v3/videos?uploadType=resumable&part=snippet,status", [
                'snippet' => [
                    'title' => $title,
                    'description' => $description,
                    'categoryId' => '22', // People & Blogs
                ],
                'status' => [
                    'privacyStatus' => 'public',
                    'selfDeclaredMadeForKids' => false,
                ],
            ]);

        if ($initResponse->failed()) {
            Log::error('YouTube upload init failed', [
                'status' => $initResponse->status(),
                'body' => $initResponse->body(),
            ]);
            throw new \Exception('YouTube API error: '.$initResponse->body());
        }

        $uploadUrl = $initResponse->header('Location');

        if (! $uploadUrl) {
            throw new \Exception('YouTube did not return an upload URL');
        }

        Log::info('YouTube upload initialized', ['uploadUrl' => $uploadUrl]);

        // Step 3: Upload the video content
        $uploadResponse = Http::withToken($this->accessToken)
            ->withHeaders([
                'Content-Type' => $media->mime_type,
                'Content-Length' => $fileSize,
            ])
            ->timeout(600)
            ->withBody($videoContent, $media->mime_type)
            ->put($uploadUrl);

        if ($uploadResponse->failed()) {
            Log::error('YouTube video upload failed', [
                'status' => $uploadResponse->status(),
                'body' => $uploadResponse->body(),
            ]);
            throw new \Exception('YouTube upload error: '.$uploadResponse->body());
        }

        $data = $uploadResponse->json();

        Log::info('YouTube upload response', ['data' => $data]);

        $videoId = $data['id'] ?? null;

        if (! $videoId) {
            throw new \Exception('YouTube did not return a video ID');
        }

        return [
            'id' => $videoId,
            'url' => "https://www.youtube.com/shorts/{$videoId}",
        ];
    }

    private function buildTitle(string $content): string
    {
        // YouTube title max is 100 characters
        // For Shorts, add #Shorts hashtag to help YouTube classify it
        $maxLength = 100;
        $shortsTag = ' #Shorts';
        $availableLength = $maxLength - strlen($shortsTag);

        // Get first line or first sentence as title
        $title = strtok($content, "\n");
        $title = strtok($title, '.');

        if (strlen($title) > $availableLength) {
            $title = substr($title, 0, $availableLength - 3).'...';
        }

        return $title.$shortsTag;
    }

    private function refreshToken(SocialAccount $account): void
    {
        if (! $account->refresh_token) {
            throw new \Exception('No refresh token available for YouTube account');
        }

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'grant_type' => 'refresh_token',
            'refresh_token' => $account->refresh_token,
        ]);

        if ($response->failed()) {
            Log::error('YouTube token refresh failed', ['body' => $response->body()]);
            throw new \Exception('Failed to refresh YouTube token: '.$response->body());
        }

        $data = $response->json();

        $account->update([
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? $account->refresh_token,
            'token_expires_at' => isset($data['expires_in']) ? now()->addSeconds($data['expires_in']) : null,
        ]);

        Log::info('YouTube token refreshed successfully');
    }
}
