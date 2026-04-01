<?php

declare(strict_types=1);

namespace App\Services\Social;

use App\Enums\PostPlatform\ContentType;
use App\Enums\SocialAccount\Platform;
use App\Exceptions\Social\PinterestPublishException;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Services\Media\MediaOptimizer;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PinterestPublisher
{
    private const API_BASE = 'https://api.pinterest.com/v5';

    public function publish(PostPlatform $postPlatform): array
    {
        $account = $postPlatform->socialAccount;

        if ($account->is_token_expired || $account->is_token_expiring_soon) {
            $this->refreshToken($account);
            $account->refresh();
        }

        return match ($postPlatform->content_type) {
            ContentType::PinterestPin => $this->publishImagePin($postPlatform),
            ContentType::PinterestVideoPin => $this->publishVideoPin($postPlatform),
            ContentType::PinterestCarousel => $this->publishCarousel($postPlatform),
            default => throw new \Exception("Unsupported content type: {$postPlatform->content_type->value}"),
        };
    }

    private function publishImagePin(PostPlatform $postPlatform): array
    {
        $account = $postPlatform->socialAccount;
        $media = $postPlatform->media->first();

        if (! $media) {
            throw new \Exception('Pinterest requires at least one image');
        }

        $boardId = data_get($postPlatform->meta, 'board_id') ?? data_get($account->meta, 'default_board_id') ?? null;

        if (! $boardId) {
            throw new \Exception('Pinterest board_id is required');
        }

        // Download and optimize image
        $tempFile = tempnam(sys_get_temp_dir(), 'pin_image_');

        try {
            Http::withOptions(['sink' => $tempFile])->timeout(600)->get($media->url);

            $detectedMime = mime_content_type($tempFile) ?: '';
            if (str_starts_with($detectedMime, 'image/') && ! str_starts_with($detectedMime, 'image/gif')) {
                $optimizer = app(MediaOptimizer::class);
                $optimizedPath = $optimizer->optimizeImage($tempFile, Platform::Pinterest);
                @unlink($tempFile);
                $tempFile = $optimizedPath;
            }

            $imageBase64 = base64_encode(file_get_contents($tempFile));
        } finally {
            @unlink($tempFile);
        }

        $payload = [
            'board_id' => $boardId,
            'media_source' => [
                'source_type' => 'image_base64',
                'content_type' => 'image/jpeg',
                'data' => $imageBase64,
            ],
        ];

        if ($postPlatform->content) {
            $payload['description'] = $postPlatform->content;
        }

        if (! empty(data_get($postPlatform->meta, 'title'))) {
            $payload['title'] = substr(data_get($postPlatform->meta, 'title'), 0, 100);
        }

        if (! empty(data_get($postPlatform->meta, 'link'))) {
            $payload['link'] = data_get($postPlatform->meta, 'link');
        }

        if (! empty(data_get($postPlatform->meta, 'alt_text'))) {
            $payload['alt_text'] = substr(data_get($postPlatform->meta, 'alt_text'), 0, 500);
        }

        $response = Http::withToken($account->access_token)
            ->post(self::API_BASE.'/pins', $payload);

        if ($response->failed()) {
            Log::error('Pinterest pin creation failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            $this->handleApiError($response);
        }

        $data = $response->json();

        return [
            'id' => data_get($data, 'id'),
            'url' => 'https://pinterest.com/pin/'.data_get($data, 'id'),
        ];
    }

    private function publishVideoPin(PostPlatform $postPlatform): array
    {
        $account = $postPlatform->socialAccount;
        $media = $postPlatform->media->first();

        if (! $media) {
            throw new \Exception('Pinterest requires a video');
        }

        $boardId = data_get($postPlatform->meta, 'board_id') ?? data_get($account->meta, 'default_board_id') ?? null;

        if (! $boardId) {
            throw new \Exception('Pinterest board_id is required');
        }

        // Step 1: Register media upload
        $registerResponse = Http::withToken($account->access_token)
            ->post(self::API_BASE.'/media', [
                'media_type' => 'video',
            ]);

        if ($registerResponse->failed()) {
            Log::error('Pinterest media registration failed', [
                'status' => $registerResponse->status(),
                'body' => $registerResponse->body(),
            ]);
            $this->handleApiError($registerResponse);
        }

        $registerData = $registerResponse->json();
        $mediaId = $registerData['media_id'];

        // Step 2: Upload video to S3
        $uploadParams = $registerData['upload_parameters'] ?? [];
        $uploadUrl = $registerData['upload_url'] ?? null;

        if (! $uploadUrl) {
            throw new \Exception('Pinterest did not return upload URL');
        }

        // Build multipart form data
        $multipart = [];
        foreach ($uploadParams as $key => $value) {
            $multipart[] = ['name' => $key, 'contents' => $value];
        }

        // Download video to temp file (memory-safe)
        $tempFile = tempnam(sys_get_temp_dir(), 'pin_video_');
        Http::withOptions(['sink' => $tempFile])->timeout(600)->get($media->url);

        $videoContent = fopen($tempFile, 'r');

        if ($videoContent === false) {
            @unlink($tempFile);
            throw new \Exception('Failed to read video file');
        }

        $multipart[] = [
            'name' => 'file',
            'contents' => $videoContent,
            'filename' => basename($media->url),
        ];

        $uploadResponse = Http::asMultipart()
            ->timeout(600)
            ->post($uploadUrl, $multipart);

        if (is_resource($videoContent)) {
            fclose($videoContent);
        }
        @unlink($tempFile);

        if ($uploadResponse->failed()) {
            Log::error('Pinterest video upload failed', [
                'status' => $uploadResponse->status(),
                'body' => $uploadResponse->body(),
            ]);
            throw new \Exception('Pinterest video upload failed');
        }

        // Step 3: Wait for processing
        $this->waitForMediaProcessing($account, $mediaId);

        // Step 4: Create pin with video
        $payload = [
            'board_id' => $boardId,
            'media_source' => [
                'source_type' => 'video_id',
                'media_id' => $mediaId,
            ],
        ];

        if ($postPlatform->content) {
            $payload['description'] = $postPlatform->content;
        }

        if (! empty(data_get($postPlatform->meta, 'title'))) {
            $payload['title'] = substr(data_get($postPlatform->meta, 'title'), 0, 100);
        }

        if (! empty(data_get($postPlatform->meta, 'link'))) {
            $payload['link'] = data_get($postPlatform->meta, 'link');
        }

        if (! empty(data_get($postPlatform->meta, 'cover_image_url'))) {
            $payload['media_source']['cover_image_url'] = data_get($postPlatform->meta, 'cover_image_url');
        }

        $response = Http::withToken($account->access_token)
            ->post(self::API_BASE.'/pins', $payload);

        if ($response->failed()) {
            Log::error('Pinterest video pin creation failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            $this->handleApiError($response);
        }

        $data = $response->json();

        return [
            'id' => data_get($data, 'id'),
            'url' => 'https://pinterest.com/pin/'.data_get($data, 'id'),
        ];
    }

    private function publishCarousel(PostPlatform $postPlatform): array
    {
        $account = $postPlatform->socialAccount;
        $medias = $postPlatform->media;

        if ($medias->count() < 2 || $medias->count() > 5) {
            throw new \Exception('Pinterest carousel requires 2-5 images');
        }

        $boardId = data_get($postPlatform->meta, 'board_id') ?? data_get($account->meta, 'default_board_id') ?? null;

        if (! $boardId) {
            throw new \Exception('Pinterest board_id is required');
        }

        $items = $medias->map(fn ($media) => [
            'url' => $media->url,
        ])->toArray();

        $payload = [
            'board_id' => $boardId,
            'media_source' => [
                'source_type' => 'multiple_image_urls',
                'items' => $items,
            ],
        ];

        if ($postPlatform->content) {
            $payload['description'] = $postPlatform->content;
        }

        if (! empty(data_get($postPlatform->meta, 'title'))) {
            $payload['title'] = substr(data_get($postPlatform->meta, 'title'), 0, 100);
        }

        if (! empty(data_get($postPlatform->meta, 'link'))) {
            $payload['link'] = data_get($postPlatform->meta, 'link');
        }

        $response = Http::withToken($account->access_token)
            ->post(self::API_BASE.'/pins', $payload);

        if ($response->failed()) {
            Log::error('Pinterest carousel creation failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            $this->handleApiError($response);
        }

        $data = $response->json();

        return [
            'id' => data_get($data, 'id'),
            'url' => 'https://pinterest.com/pin/'.data_get($data, 'id'),
        ];
    }

    private function waitForMediaProcessing(SocialAccount $account, string $mediaId, int $maxAttempts = 30): void
    {
        for ($i = 0; $i < $maxAttempts; $i++) {
            $response = Http::withToken($account->access_token)
                ->get(self::API_BASE."/media/{$mediaId}");

            if ($response->failed()) {
                Log::warning('Pinterest media status check failed', [
                    'media_id' => $mediaId,
                    'attempt' => $i,
                    'body' => $response->body(),
                ]);
                sleep(3);

                continue;
            }

            $data = $response->json();
            $status = data_get($data, 'status', 'unknown');

            if ($status === 'succeeded') {
                return;
            }

            if ($status === 'failed') {
                $failureCode = data_get($data, 'failure_code', 'unknown');
                throw new \Exception("Pinterest media processing failed: {$failureCode}");
            }

            sleep(3);
        }

        throw new \Exception("Pinterest media processing timeout after {$maxAttempts} attempts");
    }

    public function refreshToken(SocialAccount $account): void
    {
        $response = Http::asForm()
            ->withBasicAuth(
                config('services.pinterest.client_id'),
                config('services.pinterest.client_secret')
            )
            ->post(self::API_BASE.'/oauth/token', [
                'grant_type' => 'refresh_token',
                'refresh_token' => $account->refresh_token,
            ]);

        if ($response->failed()) {
            Log::error('Pinterest token refresh failed', ['body' => $response->body()]);
            $this->handleApiError($response);
        }

        $data = $response->json();

        $account->update([
            'access_token' => data_get($data, 'access_token'),
            'refresh_token' => data_get($data, 'refresh_token', $account->refresh_token),
            'token_expires_at' => data_get($data, 'expires_in') ? now()->addSeconds(data_get($data, 'expires_in')) : now()->addDays(30),
        ]);
    }

    /**
     * Get user's boards for board selection.
     */
    public function getBoards(SocialAccount $account): array
    {
        if ($account->is_token_expired || $account->is_token_expiring_soon) {
            $this->refreshToken($account);
            $account->refresh();
        }

        $response = Http::withToken($account->access_token)
            ->get(self::API_BASE.'/boards', [
                'page_size' => 100,
            ]);

        if ($response->failed()) {
            Log::error('Pinterest get boards failed', ['body' => $response->body()]);
            $this->handleApiError($response);
        }

        return $response->json()['items'] ?? [];
    }

    private function handleApiError(Response $response): never
    {
        throw PinterestPublishException::fromApiResponse($response);
    }
}
