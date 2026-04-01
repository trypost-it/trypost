<?php

declare(strict_types=1);

namespace App\Services\Social;

use App\Exceptions\Social\MastodonPublishException;
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
            $mediaId = $this->uploadMedia($account, $instance, $media->url, $media->original_filename);
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
            'id' => data_get($data, 'id'),
            'url' => data_get($data, 'url'),
        ]);

        return [
            'id' => data_get($data, 'id'),
            'url' => data_get($data, 'url'),
        ];
    }

    private function uploadMedia(SocialAccount $account, string $instance, string $url, ?string $filename): ?string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'masto_media_');

        try {
            Http::withOptions(['sink' => $tempFile])->timeout(600)->get($url);

            if (filesize($tempFile) === 0) {
                Log::error('Mastodon failed to download media', ['url' => $url]);

                return null;
            }

            $name = $filename ?? basename(parse_url($url, PHP_URL_PATH));
            if (empty($name)) {
                $name = 'media';
            }

            $response = Http::withToken($account->access_token)
                ->attach('file', fopen($tempFile, 'r'), $name)
                ->post("{$instance}/api/v1/media");

            if ($response->failed()) {
                Log::error('Mastodon media upload failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $data = $response->json();

            Log::info('Mastodon media uploaded', ['id' => data_get($data, 'id')]);

            return data_get($data, 'id');
        } catch (\Exception $e) {
            Log::error('Mastodon media upload error', [
                'error' => $e->getMessage(),
                'url' => $url,
            ]);

            return null;
        } finally {
            @unlink($tempFile);
        }
    }

    private function handleApiError(Response $response): void
    {
        throw MastodonPublishException::fromApiResponse($response);
    }
}
