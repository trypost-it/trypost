<?php

declare(strict_types=1);

namespace App\Services\Social;

use App\Models\PostPlatform;
use App\Services\Social\Concerns\HasSocialHttpClient;
use Illuminate\Support\Facades\Log;

class MastodonAnalytics
{
    use HasSocialHttpClient;

    public function fetchPostMetrics(PostPlatform $postPlatform): array
    {
        $account = $postPlatform->socialAccount;

        if (! $account || ! $postPlatform->platform_post_id) {
            return ['unsupported' => true, 'reason' => 'missing_post_id'];
        }

        $instance = data_get($account->meta, 'instance', 'https://mastodon.social');

        $response = $this->socialHttp()
            ->withToken($account->access_token)
            ->get("{$instance}/api/v1/statuses/{$postPlatform->platform_post_id}");

        if ($response->failed()) {
            Log::warning('Mastodon post metrics fetch failed', [
                'body' => $this->redactResponseBody($response->body()),
            ]);

            return ['unsupported' => true, 'reason' => 'api_error'];
        }

        $status = $response->json();

        return [
            ['label' => 'Favourites', 'value' => data_get($status, 'favourites_count', 0)],
            ['label' => 'Reblogs', 'value' => data_get($status, 'reblogs_count', 0)],
            ['label' => 'Replies', 'value' => data_get($status, 'replies_count', 0)],
        ];
    }
}
