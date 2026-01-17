<?php

namespace App\Services\Social;

use App\Exceptions\TokenExpiredException;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class YouTubePublisher
{
    /**
     * Google/YouTube API error codes that indicate token issues.
     *
     * @see https://developers.google.com/youtube/v3/docs/errors
     */
    private const TOKEN_ERROR_CODES = [
        'invalid_grant',
        'invalid_token',
        'unauthorized',
    ];

    private const TOKEN_ERROR_REASONS = [
        'authError',
        'forbidden',
        'unauthorized',
    ];

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
            $this->handleApiError($initResponse, 'YouTube API error');
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
            $this->handleApiError($uploadResponse, 'YouTube upload error');
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
            throw new TokenExpiredException('No refresh token available for YouTube account');
        }

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'grant_type' => 'refresh_token',
            'refresh_token' => $account->refresh_token,
        ]);

        if ($response->failed()) {
            Log::error('YouTube token refresh failed', ['body' => $response->body()]);
            $this->handleApiError($response, 'Failed to refresh YouTube token');
        }

        $data = $response->json();

        $account->update([
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? $account->refresh_token,
            'token_expires_at' => isset($data['expires_in']) ? now()->addSeconds($data['expires_in']) : null,
        ]);

        Log::info('YouTube token refreshed successfully');
    }

    private function handleApiError(Response $response, string $context): void
    {
        $body = $response->json() ?? [];

        // Google OAuth error format
        $errorCode = $body['error'] ?? null;
        $errorDescription = $body['error_description'] ?? null;

        // YouTube API error format
        $error = $body['error'] ?? [];
        if (is_array($error)) {
            $errors = $error['errors'] ?? [];
            $reason = $errors[0]['reason'] ?? null;
            $message = $error['message'] ?? $errorDescription ?? $response->body();
        } else {
            $reason = null;
            $message = $errorDescription ?? $response->body();
        }

        $isTokenError = $response->status() === 401
            || in_array($errorCode, self::TOKEN_ERROR_CODES)
            || in_array($reason, self::TOKEN_ERROR_REASONS);

        if ($isTokenError) {
            throw new TokenExpiredException(
                "{$context}: {$message}",
                is_string($errorCode) ? $errorCode : $reason
            );
        }

        throw new \Exception("{$context}: {$message}");
    }
}
