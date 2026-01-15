<?php

namespace App\Services\Social;

use App\Models\PostPlatform;
use Illuminate\Support\Facades\Http;

class XPublisher
{
    public function publish(PostPlatform $postPlatform): array
    {
        $account = $postPlatform->socialAccount;
        $accessToken = $account->access_token;

        $payload = [
            'text' => $postPlatform->content,
        ];

        $media = $postPlatform->media;

        if ($media->isNotEmpty()) {
            $mediaIds = [];

            foreach ($media as $mediaItem) {
                $uploadedMediaId = $this->uploadMedia($accessToken, $mediaItem);
                if ($uploadedMediaId) {
                    $mediaIds[] = $uploadedMediaId;
                }
            }

            if (! empty($mediaIds)) {
                $payload['media'] = [
                    'media_ids' => $mediaIds,
                ];
            }
        }

        $response = Http::withToken($accessToken)
            ->post('https://api.twitter.com/2/tweets', $payload);

        if ($response->failed()) {
            throw new \Exception("X API error: " . $response->body());
        }

        $data = $response->json();
        $tweetId = $data['data']['id'] ?? null;

        return [
            'id' => $tweetId ?? 'unknown',
            'url' => $tweetId ? "https://x.com/{$account->username}/status/{$tweetId}" : null,
        ];
    }

    private function uploadMedia(string $accessToken, $mediaItem): ?string
    {
        return null;
    }
}
