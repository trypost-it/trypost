<?php

namespace App\Services\Social;

use App\Models\PostPlatform;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class TikTokPublisher
{
    public function publish(PostPlatform $postPlatform): array
    {
        $account = $postPlatform->socialAccount;
        $accessToken = $account->access_token;

        $media = $postPlatform->media->first();

        if (! $media || $media->type->value !== 'video') {
            throw new \Exception('TikTok requires a video to publish.');
        }

        $response = Http::withToken($accessToken)
            ->post('https://open.tiktokapis.com/v2/post/publish/inbox/video/init/', [
                'source_info' => [
                    'source' => 'PULL_FROM_URL',
                    'video_url' => $media->url,
                ],
                'post_info' => [
                    'title' => $postPlatform->content,
                    'privacy_level' => 'PUBLIC_TO_EVERYONE',
                ],
            ]);

        if ($response->failed()) {
            throw new \Exception("TikTok API error: " . $response->body());
        }

        $data = $response->json();

        return [
            'id' => $data['data']['publish_id'] ?? 'unknown',
            'url' => null,
        ];
    }
}
