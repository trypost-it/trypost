<?php

declare(strict_types=1);

namespace App\Services\Social;

use App\Enums\PostPlatform\ContentType;
use App\Enums\SocialAccount\Platform;
use App\Exceptions\Social\LinkedInPublishException;
use App\Exceptions\TokenExpiredException;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Services\Media\MediaOptimizer;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LinkedInPagePublisher
{
    private string $baseUrl = 'https://api.linkedin.com';

    private string $apiVersion = '202601';

    private string $accessToken;

    private SocialAccount $account;

    private bool $hasRetried = false;

    public function publish(PostPlatform $postPlatform): array
    {
        $this->account = $postPlatform->socialAccount;
        $this->hasRetried = false;

        if ($this->account->is_token_expired || $this->account->is_token_expiring_soon) {
            $this->refreshToken($this->account);
            $this->account->refresh();
        }

        $this->accessToken = $this->account->access_token;

        $organizationId = $this->account->meta['organization_id'] ?? null;

        if (! $organizationId) {
            throw new \Exception('LinkedIn Page organization ID not configured');
        }

        $organizationUrn = "urn:li:organization:{$organizationId}";
        $contentType = $postPlatform->content_type;

        try {
            return match ($contentType) {
                ContentType::LinkedInPageCarousel => $this->publishCarousel($organizationUrn, $postPlatform->content, $postPlatform->media, $this->account),
                ContentType::LinkedInPagePost => $this->publishPost($organizationUrn, $postPlatform->content, $postPlatform->media, $this->account),
                default => throw new \Exception("Unsupported LinkedIn Page content type: {$contentType?->value}"),
            };
        } catch (TokenExpiredException $e) {
            return $this->retryWithRefresh($postPlatform, $e);
        }
    }

    private function retryWithRefresh(PostPlatform $postPlatform, TokenExpiredException $originalException): array
    {
        if ($this->hasRetried) {
            throw $originalException;
        }

        $this->hasRetried = true;

        Log::info('LinkedIn Page token rejected, attempting refresh and retry', [
            'account_id' => $this->account->id,
        ]);

        try {
            $this->refreshToken($this->account);
            $this->account->refresh();
            $this->accessToken = $this->account->access_token;

            $organizationId = $this->account->meta['organization_id'] ?? null;
            $organizationUrn = "urn:li:organization:{$organizationId}";
            $contentType = $postPlatform->content_type;

            return match ($contentType) {
                ContentType::LinkedInPageCarousel => $this->publishCarousel($organizationUrn, $postPlatform->content, $postPlatform->media, $this->account),
                ContentType::LinkedInPagePost => $this->publishPost($organizationUrn, $postPlatform->content, $postPlatform->media, $this->account),
                default => throw new \Exception("Unsupported LinkedIn Page content type: {$contentType?->value}"),
            };
        } catch (\Throwable $e) {
            Log::error('LinkedIn Page refresh failed during retry', [
                'account_id' => $this->account->id,
                'error' => $e->getMessage(),
            ]);
            throw $originalException;
        }
    }

    private function publishPost(string $organizationUrn, ?string $content, $media, $account): array
    {
        $payload = [
            'author' => $organizationUrn,
            'commentary' => $content ?? '',
            'visibility' => 'PUBLIC',
            'distribution' => [
                'feedDistribution' => 'MAIN_FEED',
                'targetEntities' => [],
                'thirdPartyDistributionChannels' => [],
            ],
            'lifecycleState' => 'PUBLISHED',
        ];

        if ($media->isNotEmpty()) {
            $firstMedia = $media->first();
            $mediaUrn = $this->uploadMedia($firstMedia, $organizationUrn);

            if ($mediaUrn) {
                $payload['content'] = [
                    'media' => [
                        'id' => $mediaUrn,
                    ],
                ];
            }
        }

        Log::info('LinkedIn Page creating post', ['payload' => $payload]);

        $response = $this->getHttpClient()
            ->post("{$this->baseUrl}/rest/posts", $payload);

        if ($response->failed()) {
            Log::error('LinkedIn Page post creation failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            $this->handleApiError($response);
        }

        $postId = $response->header('x-restli-id');

        // Build company page URL
        $username = $account->username;
        $postUrl = $username
            ? "https://www.linkedin.com/company/{$username}/posts/"
            : "https://www.linkedin.com/feed/update/{$postId}";

        return [
            'id' => $postId ?? 'unknown',
            'url' => $postId ? $postUrl : null,
        ];
    }

    private function publishCarousel(string $organizationUrn, ?string $content, $mediaCollection, $account): array
    {
        Log::info('LinkedIn Page publishing carousel', [
            'owner' => $organizationUrn,
            'media_count' => $mediaCollection->count(),
        ]);

        // Upload images and build carousel items
        $carouselItems = [];

        foreach ($mediaCollection as $media) {
            if (! $media->isImage()) {
                continue;
            }

            $imageUrn = $this->uploadImage($media, $organizationUrn);

            if ($imageUrn) {
                $carouselItems[] = [
                    'altText' => $media->original_filename ?? 'Carousel image',
                    'media' => $imageUrn,
                ];
            }
        }

        if (empty($carouselItems)) {
            throw new \Exception('No valid images for LinkedIn Page carousel');
        }

        $payload = [
            'author' => $organizationUrn,
            'commentary' => $content ?? '',
            'visibility' => 'PUBLIC',
            'distribution' => [
                'feedDistribution' => 'MAIN_FEED',
                'targetEntities' => [],
                'thirdPartyDistributionChannels' => [],
            ],
            'lifecycleState' => 'PUBLISHED',
            'content' => [
                'multiImage' => [
                    'images' => $carouselItems,
                ],
            ],
        ];

        Log::info('LinkedIn Page creating carousel post', ['payload' => $payload]);

        $response = $this->getHttpClient()
            ->post("{$this->baseUrl}/rest/posts", $payload);

        if ($response->failed()) {
            Log::error('LinkedIn Page carousel post creation failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            $this->handleApiError($response);
        }

        $postId = $response->header('x-restli-id');

        // Build company page URL
        $username = $account->username;
        $postUrl = $username
            ? "https://www.linkedin.com/company/{$username}/posts/"
            : "https://www.linkedin.com/feed/update/{$postId}";

        return [
            'id' => $postId ?? 'unknown',
            'url' => $postId ? $postUrl : null,
        ];
    }

    private function getHttpClient(): PendingRequest
    {
        return Http::withToken($this->accessToken)
            ->withHeaders([
                'X-Restli-Protocol-Version' => '2.0.0',
                'LinkedIn-Version' => $this->apiVersion,
                'Content-Type' => 'application/json',
            ])
            ->timeout(300);
    }

    private function uploadMedia($mediaItem, string $ownerUrn): ?string
    {
        $mimeType = $mediaItem->mime_type;
        $isVideo = str_starts_with($mimeType, 'video/');
        $isImage = str_starts_with($mimeType, 'image/');

        if ($isVideo) {
            return $this->uploadVideo($mediaItem, $ownerUrn);
        }

        if ($isImage) {
            return $this->uploadImage($mediaItem, $ownerUrn);
        }

        return null;
    }

    private function uploadImage($mediaItem, string $ownerUrn): ?string
    {
        Log::info('LinkedIn Page initializing image upload', ['owner' => $ownerUrn]);

        // Step 1: Initialize upload
        $initResponse = $this->getHttpClient()
            ->post("{$this->baseUrl}/rest/images?action=initializeUpload", [
                'initializeUploadRequest' => [
                    'owner' => $ownerUrn,
                ],
            ]);

        if ($initResponse->failed()) {
            Log::error('LinkedIn Page image init failed', ['body' => $initResponse->body()]);
            $this->handleApiError($initResponse);
        }

        $initData = $initResponse->json();
        $uploadUrl = $initData['value']['uploadUrl'] ?? null;
        $imageUrn = $initData['value']['image'] ?? null;

        if (! $uploadUrl || ! $imageUrn) {
            throw new \Exception('LinkedIn Page image upload init missing uploadUrl or image URN');
        }

        Log::info('LinkedIn Page image init success', ['imageUrn' => $imageUrn]);

        // Step 2: Download and optimize image
        $tempFile = tempnam(sys_get_temp_dir(), 'lip_image_');

        try {
            Http::withOptions(['sink' => $tempFile])->timeout(600)->get($mediaItem->url);

            $detectedMime = mime_content_type($tempFile) ?: '';
            if (str_starts_with($detectedMime, 'image/') && ! str_starts_with($detectedMime, 'image/gif')) {
                $optimizer = app(MediaOptimizer::class);
                $optimizedPath = $optimizer->optimizeImage($tempFile, Platform::LinkedInPage);
                @unlink($tempFile);
                $tempFile = $optimizedPath;
            }

            $imageContent = file_get_contents($tempFile);

            $uploadResponse = Http::withToken($this->accessToken)
                ->withHeaders([
                    'Content-Type' => 'application/octet-stream',
                ])
                ->withBody($imageContent, 'application/octet-stream')
                ->put($uploadUrl);

            if ($uploadResponse->failed()) {
                Log::error('LinkedIn Page image upload failed', ['body' => $uploadResponse->body()]);
                $this->handleApiError($uploadResponse);
            }

            Log::info('LinkedIn Page image upload success', ['imageUrn' => $imageUrn]);

            return $imageUrn;
        } finally {
            @unlink($tempFile);
        }
    }

    private function uploadVideo($mediaItem, string $ownerUrn): ?string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'lip_video_');

        try {
            return $this->doUploadVideo($tempFile, $mediaItem, $ownerUrn);
        } finally {
            @unlink($tempFile);
        }
    }

    private function doUploadVideo(string $tempFile, $mediaItem, string $ownerUrn): ?string
    {
        Http::withOptions(['sink' => $tempFile])->timeout(600)->get($mediaItem->url);

        $fileSize = (int) filesize($tempFile);

        Log::info('LinkedIn Page initializing video upload', [
            'owner' => $ownerUrn,
            'fileSize' => $fileSize,
        ]);

        // Step 1: Initialize upload
        $initResponse = $this->getHttpClient()
            ->post("{$this->baseUrl}/rest/videos?action=initializeUpload", [
                'initializeUploadRequest' => [
                    'owner' => $ownerUrn,
                    'fileSizeBytes' => $fileSize,
                    'uploadCaptions' => false,
                    'uploadThumbnail' => false,
                ],
            ]);

        if ($initResponse->failed()) {
            Log::error('LinkedIn Page video init failed', ['body' => $initResponse->body()]);
            $this->handleApiError($initResponse);
        }

        $initData = $initResponse->json();
        $videoUrn = $initData['value']['video'] ?? null;
        $uploadInstructions = $initData['value']['uploadInstructions'] ?? [];
        $uploadToken = $initData['value']['uploadToken'] ?? '';

        if (! $videoUrn || empty($uploadInstructions)) {
            throw new \Exception('LinkedIn Page video upload init missing video URN or upload instructions');
        }

        Log::info('LinkedIn Page video init success', [
            'videoUrn' => $videoUrn,
            'chunks' => count($uploadInstructions),
        ]);

        // Step 2: Upload chunks
        $uploadedPartIds = [];

        foreach ($uploadInstructions as $index => $instruction) {
            $uploadUrl = $instruction['uploadUrl'];
            $firstByte = $instruction['firstByte'];
            $lastByte = $instruction['lastByte'];

            $chunkLength = $lastByte - $firstByte + 1;
            $handle = fopen($tempFile, 'r');
            fseek($handle, $firstByte);
            $chunkData = fread($handle, $chunkLength);
            fclose($handle);

            Log::info('LinkedIn Page uploading video chunk', [
                'index' => $index,
                'firstByte' => $firstByte,
                'lastByte' => $lastByte,
                'chunkSize' => strlen($chunkData),
            ]);

            $chunkResponse = Http::withToken($this->accessToken)
                ->withHeaders([
                    'Content-Type' => 'application/octet-stream',
                ])
                ->timeout(600)
                ->withBody($chunkData, 'application/octet-stream')
                ->put($uploadUrl);

            if ($chunkResponse->failed()) {
                Log::error('LinkedIn Page video chunk upload failed', [
                    'index' => $index,
                    'body' => $chunkResponse->body(),
                ]);
                $this->handleApiError($chunkResponse);
            }

            $etag = $chunkResponse->header('etag');
            if ($etag) {
                $uploadedPartIds[] = $etag;
            }

            Log::info('LinkedIn Page video chunk uploaded', ['index' => $index, 'etag' => $etag]);
        }

        // Step 3: Finalize upload
        Log::info('LinkedIn Page finalizing video upload', ['videoUrn' => $videoUrn]);

        $finalizeResponse = $this->getHttpClient()
            ->post("{$this->baseUrl}/rest/videos?action=finalizeUpload", [
                'finalizeUploadRequest' => [
                    'video' => $videoUrn,
                    'uploadToken' => $uploadToken,
                    'uploadedPartIds' => $uploadedPartIds,
                ],
            ]);

        if ($finalizeResponse->failed()) {
            Log::error('LinkedIn Page video finalize failed', ['body' => $finalizeResponse->body()]);
            $this->handleApiError($finalizeResponse);
        }

        Log::info('LinkedIn Page video upload finalized', ['videoUrn' => $videoUrn]);

        // Step 4: Wait for processing
        $this->waitForVideoProcessing($videoUrn);

        return $videoUrn;
    }

    private function waitForVideoProcessing(string $videoUrn, int $maxAttempts = 30): void
    {
        $encodedUrn = urlencode($videoUrn);

        for ($i = 0; $i < $maxAttempts; $i++) {
            $response = $this->getHttpClient()
                ->get("{$this->baseUrl}/rest/videos/{$encodedUrn}");

            if ($response->failed()) {
                Log::warning('LinkedIn Page video status check failed', ['attempt' => $i]);
                sleep(5);

                continue;
            }

            $data = $response->json();
            $status = data_get($data, 'status', 'UNKNOWN');

            Log::info('LinkedIn Page video processing status', ['status' => $status, 'attempt' => $i]);

            if ($status === 'AVAILABLE') {
                return;
            }

            if ($status === 'PROCESSING_FAILED') {
                throw new \Exception('LinkedIn Page video processing failed');
            }

            sleep(5);
        }

        Log::warning('LinkedIn Page video processing timeout, proceeding anyway');
    }

    private function refreshToken(SocialAccount $account): void
    {
        if (! $account->refresh_token) {
            throw new TokenExpiredException('No refresh token available for LinkedIn Page account');
        }

        $response = Http::asForm()->post('https://www.linkedin.com/oauth/v2/accessToken', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $account->refresh_token,
            'client_id' => config('services.linkedin-openid.client_id'),
            'client_secret' => config('services.linkedin-openid.client_secret'),
        ]);

        if ($response->failed()) {
            $this->handleApiError($response);
        }

        $data = $response->json();

        $account->update([
            'access_token' => data_get($data, 'access_token'),
            'refresh_token' => data_get($data, 'refresh_token', $account->refresh_token),
            'token_expires_at' => data_get($data, 'expires_in') ? now()->addSeconds(data_get($data, 'expires_in')) : null,
        ]);

        // Sync tokens to LinkedIn personal if it exists
        app(LinkedInTokenSynchronizer::class)->syncTokens($account);
    }

    private function handleApiError(Response $response): never
    {
        throw LinkedInPublishException::fromApiResponse($response);
    }
}
