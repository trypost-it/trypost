<?php

declare(strict_types=1);

namespace App\Services\Social;

use App\Models\PostPlatform;
use App\Services\Social\Concerns\HasSocialHttpClient;
use Illuminate\Support\Facades\Log;

class BlueskyAnalytics
{
    use HasSocialHttpClient;

    public function fetchPostMetrics(PostPlatform $postPlatform): array
    {
        $account = $postPlatform->socialAccount;

        if (! $account || ! $postPlatform->platform_post_id) {
            return ['unsupported' => true, 'reason' => 'missing_post_id'];
        }

        $service = data_get($account->meta, 'service', 'https://bsky.social');
        $did = $account->platform_user_id;
        $atUri = "at://{$did}/app.bsky.feed.post/{$postPlatform->platform_post_id}";

        // Public AppView API: no auth required for reading post counts.
        $response = $this->socialHttp()
            ->get("{$service}/xrpc/app.bsky.feed.getPosts", [
                'uris' => [$atUri],
            ]);

        if ($response->failed()) {
            Log::warning('Bluesky post metrics fetch failed', [
                'body' => $this->redactResponseBody($response->body()),
            ]);

            return ['unsupported' => true, 'reason' => 'api_error'];
        }

        $post = data_get($response->json(), 'posts.0', []);

        return [
            ['label' => 'Likes', 'value' => data_get($post, 'likeCount', 0)],
            ['label' => 'Reposts', 'value' => data_get($post, 'repostCount', 0)],
            ['label' => 'Quotes', 'value' => data_get($post, 'quoteCount', 0)],
            ['label' => 'Replies', 'value' => data_get($post, 'replyCount', 0)],
        ];
    }
}
