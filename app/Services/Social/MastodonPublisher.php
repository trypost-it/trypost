<?php

namespace App\Services\Social;

use App\Exceptions\TokenExpiredException;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MastodonPublisher
{
    public function publish(PostPlatform $postPlatform): array
    {
        $account = $postPlatform->socialAccount;
        $instance = $account->meta['instance'] ?? 'https://mastodon.social';

        $medias = $postPlatform->media;
        $mediaIds = [];

        // Upload media first (max 4)
        foreach ($medias->take(4) as $media) {
            $mediaId = $this->uploadMedia($account, $instance, $media->url, $media->filename);
            if ($mediaId) {
                $mediaIds[] = $mediaId;
            }
        }

        // Create status
        $payload = [
            'status' => $postPlatform->content ?? '',
            'visibility' => 'public',
        ];

        if (! empty($mediaIds)) {
            $payload['media_ids'] = $mediaIds;
        }

        Log::info('Mastodon publishing status', [
            'instance' => $instance,
            'user_id' => $account->platform_user_id,
            'has_media' => count($mediaIds) > 0,
        ]);

        $response = Http::withToken($account->access_token)
            ->post("{$instance}/api/v1/statuses", $payload);

        if ($response->failed()) {
            Log::error('Mastodon post failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            $this->handleApiError($response);
        }

        $data = $response->json();

        Log::info('Mastodon post created', [
            'id' => $data['id'],
            'url' => $data['url'],
        ]);

        return [
            'id' => $data['id'],
            'url' => $data['url'],
        ];
    }

    private function uploadMedia(SocialAccount $account, string $instance, string $url, ?string $filename): ?string
    {
        try {
            $fileContent = file_get_contents($url);
            if ($fileContent === false) {
                Log::error('Mastodon failed to read media', ['url' => $url]);

                return null;
            }

            // Determine filename from URL if not provided
            $name = $filename ?? basename(parse_url($url, PHP_URL_PATH));
            if (empty($name)) {
                $name = 'media';
            }

            $response = Http::withToken($account->access_token)
                ->attach('file', $fileContent, $name)
                ->post("{$instance}/api/v1/media");

            if ($response->failed()) {
                Log::error('Mastodon media upload failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $data = $response->json();

            Log::info('Mastodon media uploaded', ['id' => $data['id']]);

            return $data['id'];
        } catch (\Exception $e) {
            Log::error('Mastodon media upload error', [
                'error' => $e->getMessage(),
                'url' => $url,
            ]);

            return null;
        }
    }

    private function handleApiError(Response $response): void
    {
        $body = $response->json() ?? [];
        $error = $body['error'] ?? $response->body();

        if ($response->status() === 401 || $response->status() === 403) {
            throw new TokenExpiredException("Mastodon: {$error}");
        }

        throw new \Exception("Mastodon API error: {$error}");
    }
}
