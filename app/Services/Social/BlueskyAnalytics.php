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

        $did = $account->platform_user_id;
        $atUri = "at://{$did}/app.bsky.feed.post/{$postPlatform->platform_post_id}";

        // Read counts (likes, reposts, replies, quotes) live on the AT
        // Protocol AppView, not the PDS. The user's PDS requires Bearer auth
        // for this endpoint; the public AppView does not.
        $appView = (string) config('trypost.platforms.bluesky.public_appview');

        $response = $this->socialHttp()
            ->get("{$appView}/xrpc/app.bsky.feed.getPosts", [
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
            ['label' => __('analytics.metrics.likes'), 'value' => data_get($post, 'likeCount', 0)],
            ['label' => __('analytics.metrics.reposts'), 'value' => data_get($post, 'repostCount', 0)],
            ['label' => __('analytics.metrics.quotes'), 'value' => data_get($post, 'quoteCount', 0)],
            ['label' => __('analytics.metrics.replies'), 'value' => data_get($post, 'replyCount', 0)],
        ];
    }
}
