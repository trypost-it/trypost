<?php

declare(strict_types=1);

namespace App\Services\Social;

use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Services\Social\Concerns\HasSocialHttpClient;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FacebookAnalytics
{
    use HasSocialHttpClient;

    private string $baseUrl = 'https://graph.facebook.com/v25.0';

    private string $accessToken;

    public function getMetrics(SocialAccount $account, ?CarbonInterface $since = null, ?CarbonInterface $until = null): array
    {
        $since ??= now()->subDays(7);
        $until ??= now();

        $cacheKey = "analytics:facebook:{$account->id}:{$since->format('Y-m-d')}:{$until->format('Y-m-d')}";
        $cacheTtl = app()->isProduction() ? 3600 : 1;

        return Cache::remember($cacheKey, $cacheTtl, function () use ($account, $since, $until) {
            return $this->fetchMetricsFromApi($account, $since, $until);
        });
    }

    public function fetchPostMetrics(PostPlatform $postPlatform): array
    {
        $account = $postPlatform->socialAccount;

        if (! $account || ! $postPlatform->platform_post_id) {
            return ['unsupported' => true, 'reason' => 'missing_post_id'];
        }

        $response = $this->socialHttp()
            ->get("{$this->baseUrl}/{$postPlatform->platform_post_id}/insights", [
                'metric' => 'post_impressions,post_impressions_unique,post_reactions_like_total,post_clicks',
                'access_token' => $account->access_token,
            ]);

        if ($response->failed()) {
            Log::warning('Facebook post metrics fetch failed', [
                'body' => $this->redactResponseBody($response->body()),
            ]);

            return ['unsupported' => true, 'reason' => 'api_error'];
        }

        $insights = data_get($response->json(), 'data', []);

        return collect($insights)
            ->map(fn (array $item) => [
                'label' => ucfirst(str_replace('_', ' ', data_get($item, 'name', ''))),
                'value' => (int) data_get($item, 'values.0.value', 0),
            ])
            ->values()
            ->all();
    }

    private function fetchMetricsFromApi(SocialAccount $account, CarbonInterface $since, CarbonInterface $until): array
    {
        $this->accessToken = $account->access_token;

        $response = $this->getHttpClient()
            ->get("{$this->baseUrl}/{$account->platform_user_id}/insights", [
                'metric' => 'page_total_media_view_unique,post_total_media_view_unique,page_post_engagements,page_daily_follows,page_media_view',
                'period' => 'day',
                'since' => $since->startOfDay()->unix(),
                'until' => $until->endOfDay()->unix(),
                'access_token' => $this->accessToken,
            ]);

        if ($response->failed()) {
            Log::warning('Facebook page insights fetch failed', [
                'body' => $this->redactResponseBody($response->body()),
            ]);

            return [];
        }

        $data = data_get($response->json(), 'data', []);
        $metrics = [];

        foreach ($data as $metric) {
            $name = data_get($metric, 'name');
            $values = data_get($metric, 'values', []);

            if (empty($values)) {
                continue;
            }

            $total = collect($values)->sum('value');

            $label = match ($name) {
                'page_total_media_view_unique' => 'Page Reach',
                'post_total_media_view_unique' => 'Posts Reach',
                'page_post_engagements' => 'Posts Engagement',
                'page_daily_follows' => 'Page Followers',
                'page_media_view' => 'Page Views',
                default => ucfirst(str_replace('_', ' ', $name)),
            };

            $metrics[] = ['label' => $label, 'value' => $total];
        }

        return $metrics;
    }

    private function getHttpClient(): PendingRequest
    {
        return $this->socialHttp();
    }
}
