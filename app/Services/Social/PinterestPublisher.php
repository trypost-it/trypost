<?php

namespace App\Services\Social;

use App\Enums\PostPlatform\ContentType;
use App\Exceptions\TokenExpiredException;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PinterestPublisher
{
    private const API_BASE = 'https://api.pinterest.com/v5';

    /**
     * Pinterest API error codes that indicate token issues.
     */
    private const TOKEN_ERROR_CODES = [
        1, // Invalid access token
        2, // Access token has expired
    ];

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

        $boardId = $postPlatform->meta['board_id'] ?? $account->meta['default_board_id'] ?? null;

        if (! $boardId) {
            throw new \Exception('Pinterest board_id is required');
        }

        Log::info('Pinterest publishing image pin', [
            'user_id' => $account->platform_user_id,
            'board_id' => $boardId,
            'image_url' => $media->url,
        ]);

        $payload = [
            'board_id' => $boardId,
            'media_source' => [
                'source_type' => 'image_url',
                'url' => $media->url,
            ],
        ];

        if ($postPlatform->content) {
            $payload['description'] = $postPlatform->content;
        }

        if (! empty($postPlatform->meta['title'])) {
            $payload['title'] = substr($postPlatform->meta['title'], 0, 100);
        }

        if (! empty($postPlatform->meta['link'])) {
            $payload['link'] = $postPlatform->meta['link'];
        }

        if (! empty($postPlatform->meta['alt_text'])) {
            $payload['alt_text'] = substr($postPlatform->meta['alt_text'], 0, 500);
        }

        $response = Http::withToken($account->access_token)
            ->post(self::API_BASE.'/pins', $payload);

        if ($response->failed()) {
            Log::error('Pinterest pin creation failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            $this->handleApiError($response, 'Pinterest API error');
        }

        $data = $response->json();

        Log::info('Pinterest pin created successfully', ['pin_id' => $data['id']]);

        return [
            'id' => $data['id'],
            'url' => "https://pinterest.com/pin/{$data['id']}",
        ];
    }

    private function publishVideoPin(PostPlatform $postPlatform): array
    {
        $account = $postPlatform->socialAccount;
        $media = $postPlatform->media->first();

        if (! $media) {
            throw new \Exception('Pinterest requires a video');
        }

        $boardId = $postPlatform->meta['board_id'] ?? $account->meta['default_board_id'] ?? null;

        if (! $boardId) {
            throw new \Exception('Pinterest board_id is required');
        }

        Log::info('Pinterest publishing video pin', [
            'user_id' => $account->platform_user_id,
            'board_id' => $boardId,
        ]);

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
            $this->handleApiError($registerResponse, 'Pinterest media registration error');
        }

        $registerData = $registerResponse->json();
        $mediaId = $registerData['media_id'];

        Log::info('Pinterest media registered', ['media_id' => $mediaId]);

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

        // Get video content
        $videoContent = file_get_contents($media->url);
        if ($videoContent === false) {
            throw new \Exception('Failed to read video file');
        }

        $multipart[] = [
            'name' => 'file',
            'contents' => $videoContent,
            'filename' => basename($media->url),
        ];

        $uploadResponse = Http::asMultipart()
            ->post($uploadUrl, $multipart);

        if ($uploadResponse->failed()) {
            Log::error('Pinterest video upload failed', [
                'status' => $uploadResponse->status(),
                'body' => $uploadResponse->body(),
            ]);
            throw new \Exception('Pinterest video upload failed');
        }

        Log::info('Pinterest video uploaded');

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

        if (! empty($postPlatform->meta['title'])) {
            $payload['title'] = substr($postPlatform->meta['title'], 0, 100);
        }

        if (! empty($postPlatform->meta['link'])) {
            $payload['link'] = $postPlatform->meta['link'];
        }

        if (! empty($postPlatform->meta['cover_image_url'])) {
            $payload['media_source']['cover_image_url'] = $postPlatform->meta['cover_image_url'];
        }

        $response = Http::withToken($account->access_token)
            ->post(self::API_BASE.'/pins', $payload);

        if ($response->failed()) {
            Log::error('Pinterest video pin creation failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            $this->handleApiError($response, 'Pinterest API error');
        }

        $data = $response->json();

        Log::info('Pinterest video pin created successfully', ['pin_id' => $data['id']]);

        return [
            'id' => $data['id'],
            'url' => "https://pinterest.com/pin/{$data['id']}",
        ];
    }

    private function publishCarousel(PostPlatform $postPlatform): array
    {
        $account = $postPlatform->socialAccount;
        $medias = $postPlatform->media;

        if ($medias->count() < 2 || $medias->count() > 5) {
            throw new \Exception('Pinterest carousel requires 2-5 images');
        }

        $boardId = $postPlatform->meta['board_id'] ?? $account->meta['default_board_id'] ?? null;

        if (! $boardId) {
            throw new \Exception('Pinterest board_id is required');
        }

        Log::info('Pinterest publishing carousel', [
            'user_id' => $account->platform_user_id,
            'board_id' => $boardId,
            'image_count' => $medias->count(),
        ]);

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

        if (! empty($postPlatform->meta['title'])) {
            $payload['title'] = substr($postPlatform->meta['title'], 0, 100);
        }

        if (! empty($postPlatform->meta['link'])) {
            $payload['link'] = $postPlatform->meta['link'];
        }

        $response = Http::withToken($account->access_token)
            ->post(self::API_BASE.'/pins', $payload);

        if ($response->failed()) {
            Log::error('Pinterest carousel creation failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            $this->handleApiError($response, 'Pinterest API error');
        }

        $data = $response->json();

        Log::info('Pinterest carousel created successfully', ['pin_id' => $data['id']]);

        return [
            'id' => $data['id'],
            'url' => "https://pinterest.com/pin/{$data['id']}",
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
            $status = $data['status'] ?? 'unknown';

            Log::info('Pinterest media processing status', [
                'media_id' => $mediaId,
                'status' => $status,
                'attempt' => $i,
            ]);

            if ($status === 'succeeded') {
                return;
            }

            if ($status === 'failed') {
                $failureCode = $data['failure_code'] ?? 'unknown';
                throw new \Exception("Pinterest media processing failed: {$failureCode}");
            }

            sleep(3);
        }

        throw new \Exception("Pinterest media processing timeout after {$maxAttempts} attempts");
    }

    public function refreshToken(SocialAccount $account): void
    {
        Log::info('Pinterest refreshing token', ['user_id' => $account->platform_user_id]);

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
            $this->handleApiError($response, 'Failed to refresh Pinterest token');
        }

        $data = $response->json();

        $account->update([
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? $account->refresh_token,
            'token_expires_at' => isset($data['expires_in']) ? now()->addSeconds($data['expires_in']) : now()->addDays(30),
        ]);

        Log::info('Pinterest token refreshed successfully');
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
            $this->handleApiError($response, 'Pinterest API error');
        }

        return $response->json()['items'] ?? [];
    }

    private function handleApiError(Response $response, string $context): void
    {
        $body = $response->json() ?? [];
        $code = $body['code'] ?? $response->status();
        $message = $body['message'] ?? $response->body();

        $isTokenError = $response->status() === 401
            || in_array($code, self::TOKEN_ERROR_CODES);

        if ($isTokenError) {
            throw new TokenExpiredException(
                "{$context}: {$message}",
                is_int($code) ? (string) $code : null
            );
        }

        throw new \Exception("{$context}: {$message}");
    }
}
