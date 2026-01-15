<?php

namespace App\Services\Social;

use App\Models\PostPlatform;
use App\Models\SocialAccount;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LinkedInPublisher
{
    private string $baseUrl = 'https://api.linkedin.com';

    private string $apiVersion = '202501';

    private string $accessToken;

    public function publish(PostPlatform $postPlatform): array
    {
        $account = $postPlatform->socialAccount;

        if ($account->is_token_expired || $account->is_token_expiring_soon) {
            $this->refreshToken($account);
            $account->refresh();
        }

        $this->accessToken = $account->access_token;

        $personUrn = "urn:li:person:{$account->platform_user_id}";

        $payload = [
            'author' => $personUrn,
            'commentary' => $postPlatform->content,
            'visibility' => 'PUBLIC',
            'distribution' => [
                'feedDistribution' => 'MAIN_FEED',
                'targetEntities' => [],
                'thirdPartyDistributionChannels' => [],
            ],
            'lifecycleState' => 'PUBLISHED',
        ];

        $media = $postPlatform->media;

        if ($media->isNotEmpty()) {
            $firstMedia = $media->first();
            $mediaUrn = $this->uploadMedia($firstMedia, $personUrn);

            if ($mediaUrn) {
                $payload['content'] = [
                    'media' => [
                        'id' => $mediaUrn,
                    ],
                ];
            }
        }

        Log::info('LinkedIn creating post', ['payload' => $payload]);

        $response = $this->getHttpClient()
            ->post("{$this->baseUrl}/rest/posts", $payload);

        if ($response->failed()) {
            Log::error('LinkedIn post creation failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('LinkedIn API error: '.$response->body());
        }

        $postId = $response->header('x-restli-id');

        return [
            'id' => $postId ?? 'unknown',
            'url' => $postId ? "https://www.linkedin.com/feed/update/{$postId}" : null,
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
        Log::info('LinkedIn initializing image upload', ['owner' => $ownerUrn]);

        // Step 1: Initialize upload
        $initResponse = $this->getHttpClient()
            ->post("{$this->baseUrl}/rest/images?action=initializeUpload", [
                'initializeUploadRequest' => [
                    'owner' => $ownerUrn,
                ],
            ]);

        if ($initResponse->failed()) {
            Log::error('LinkedIn image init failed', ['body' => $initResponse->body()]);
            throw new \Exception('Failed to initialize LinkedIn image upload: '.$initResponse->body());
        }

        $initData = $initResponse->json();
        $uploadUrl = $initData['value']['uploadUrl'] ?? null;
        $imageUrn = $initData['value']['image'] ?? null;

        if (! $uploadUrl || ! $imageUrn) {
            throw new \Exception('LinkedIn image upload init missing uploadUrl or image URN');
        }

        Log::info('LinkedIn image init success', ['imageUrn' => $imageUrn]);

        // Step 2: Upload binary data
        $imageContent = file_get_contents($mediaItem->url);

        $uploadResponse = Http::withToken($this->accessToken)
            ->withHeaders([
                'Content-Type' => 'application/octet-stream',
            ])
            ->withBody($imageContent, 'application/octet-stream')
            ->put($uploadUrl);

        if ($uploadResponse->failed()) {
            Log::error('LinkedIn image upload failed', ['body' => $uploadResponse->body()]);
            throw new \Exception('Failed to upload LinkedIn image: '.$uploadResponse->body());
        }

        Log::info('LinkedIn image upload success', ['imageUrn' => $imageUrn]);

        return $imageUrn;
    }

    private function uploadVideo($mediaItem, string $ownerUrn): ?string
    {
        $videoContent = file_get_contents($mediaItem->url);
        $fileSize = strlen($videoContent);

        Log::info('LinkedIn initializing video upload', [
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
            Log::error('LinkedIn video init failed', ['body' => $initResponse->body()]);
            throw new \Exception('Failed to initialize LinkedIn video upload: '.$initResponse->body());
        }

        $initData = $initResponse->json();
        $videoUrn = $initData['value']['video'] ?? null;
        $uploadInstructions = $initData['value']['uploadInstructions'] ?? [];
        $uploadToken = $initData['value']['uploadToken'] ?? '';

        if (! $videoUrn || empty($uploadInstructions)) {
            throw new \Exception('LinkedIn video upload init missing video URN or upload instructions');
        }

        Log::info('LinkedIn video init success', [
            'videoUrn' => $videoUrn,
            'chunks' => count($uploadInstructions),
        ]);

        // Step 2: Upload chunks
        $uploadedPartIds = [];

        foreach ($uploadInstructions as $index => $instruction) {
            $uploadUrl = $instruction['uploadUrl'];
            $firstByte = $instruction['firstByte'];
            $lastByte = $instruction['lastByte'];

            $chunkData = substr($videoContent, $firstByte, $lastByte - $firstByte + 1);

            Log::info('LinkedIn uploading video chunk', [
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
                Log::error('LinkedIn video chunk upload failed', [
                    'index' => $index,
                    'body' => $chunkResponse->body(),
                ]);
                throw new \Exception('Failed to upload LinkedIn video chunk: '.$chunkResponse->body());
            }

            $etag = $chunkResponse->header('etag');
            if ($etag) {
                $uploadedPartIds[] = $etag;
            }

            Log::info('LinkedIn video chunk uploaded', ['index' => $index, 'etag' => $etag]);
        }

        // Step 3: Finalize upload
        Log::info('LinkedIn finalizing video upload', ['videoUrn' => $videoUrn]);

        $finalizeResponse = $this->getHttpClient()
            ->post("{$this->baseUrl}/rest/videos?action=finalizeUpload", [
                'finalizeUploadRequest' => [
                    'video' => $videoUrn,
                    'uploadToken' => $uploadToken,
                    'uploadedPartIds' => $uploadedPartIds,
                ],
            ]);

        if ($finalizeResponse->failed()) {
            Log::error('LinkedIn video finalize failed', ['body' => $finalizeResponse->body()]);
            throw new \Exception('Failed to finalize LinkedIn video upload: '.$finalizeResponse->body());
        }

        Log::info('LinkedIn video upload finalized', ['videoUrn' => $videoUrn]);

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
                Log::warning('LinkedIn video status check failed', ['attempt' => $i]);
                sleep(5);

                continue;
            }

            $data = $response->json();
            $status = $data['status'] ?? 'UNKNOWN';

            Log::info('LinkedIn video processing status', ['status' => $status, 'attempt' => $i]);

            if ($status === 'AVAILABLE') {
                return;
            }

            if ($status === 'PROCESSING_FAILED') {
                throw new \Exception('LinkedIn video processing failed');
            }

            sleep(5);
        }

        Log::warning('LinkedIn video processing timeout, proceeding anyway');
    }

    private function refreshToken(SocialAccount $account): void
    {
        if (! $account->refresh_token) {
            throw new \Exception('No refresh token available for LinkedIn account');
        }

        $response = Http::asForm()->post('https://www.linkedin.com/oauth/v2/accessToken', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $account->refresh_token,
            'client_id' => config('services.linkedin.client_id'),
            'client_secret' => config('services.linkedin.client_secret'),
        ]);

        if ($response->failed()) {
            throw new \Exception('Failed to refresh LinkedIn token: '.$response->body());
        }

        $data = $response->json();

        $account->update([
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? $account->refresh_token,
            'token_expires_at' => isset($data['expires_in']) ? now()->addSeconds($data['expires_in']) : null,
        ]);
    }
}
