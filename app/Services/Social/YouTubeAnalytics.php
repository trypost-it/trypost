<?php

declare(strict_types=1);

namespace App\Services\Social;

use App\Exceptions\TokenExpiredException;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Services\Social\Concerns\HasSocialHttpClient;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class YouTubeAnalytics
{
    use HasSocialHttpClient;

    private string $baseUrl = 'https://youtubeanalytics.googleapis.com/v2';

    private string $accessToken;

    public function getMetrics(SocialAccount $account, ?CarbonInterface $since = null, ?CarbonInterface $until = null): array
    {
        $since ??= now()->subDays(7);
        $until ??= now();

        $cacheKey = "analytics:youtube:{$account->id}:{$since->format('Y-m-d')}:{$until->format('Y-m-d')}";
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

        if ($account->is_token_expired || $account->is_token_expiring_soon) {
            $this->refreshTokenWithLock($account, fn () => $this->refreshToken($account));
            $account->refresh();
        }

        $this->accessToken = $account->access_token;

        $publishedAt = $postPlatform->published_at ?? $postPlatform->created_at;
        $startDate = $publishedAt->format('Y-m-d');
        $endDate = now()->format('Y-m-d');

        $response = $this->getHttpClient()
            ->get("{$this->baseUrl}/reports", [
                'ids' => 'channel==MINE',
                'startDate' => $startDate,
                'endDate' => $endDate,
                'metrics' => 'views,estimatedMinutesWatched,averageViewDuration,likes,comments,shares',
                'filters' => "video=={$postPlatform->platform_post_id}",
            ]);

        if ($response->failed()) {
            Log::warning('YouTube post metrics fetch failed', [
                'body' => $this->redactResponseBody($response->body()),
            ]);

            return ['unsupported' => true, 'reason' => 'api_error'];
        }

        $data = $response->json();
        $columns = collect(data_get($data, 'columnHeaders', []))->pluck('name')->all();
        $row = data_get($data, 'rows.0', []);

        $labels = [
            'views' => 'Views',
            'estimatedMinutesWatched' => 'Minutes watched',
            'averageViewDuration' => 'Avg view duration (s)',
            'likes' => 'Likes',
            'comments' => 'Comments',
            'shares' => 'Shares',
        ];

        return collect($columns)
            ->map(fn (string $name, int $i) => [
                'label' => $labels[$name] ?? ucfirst($name),
                'value' => (int) ($row[$i] ?? 0),
            ])
            ->values()
            ->all();
    }

    private function fetchMetricsFromApi(SocialAccount $account, CarbonInterface $since, CarbonInterface $until): array
    {
        if ($account->is_token_expired || $account->is_token_expiring_soon) {
            $this->refreshTokenWithLock($account, fn () => $this->refreshToken($account));
            $account->refresh();
        }

        $this->accessToken = $account->access_token;

        $response = $this->getHttpClient()
            ->get("{$this->baseUrl}/reports", [
                'ids' => 'channel==MINE',
                'startDate' => $since->format('Y-m-d'),
                'endDate' => $until->format('Y-m-d'),
                'metrics' => 'views,estimatedMinutesWatched,averageViewDuration,averageViewPercentage,subscribersGained,subscribersLost,likes',
            ]);

        if ($response->failed()) {
            Log::warning('YouTube Analytics fetch failed', [
                'status' => $response->status(),
                'body' => $this->redactResponseBody($response->body()),
            ]);

            return [];
        }

        $json = $response->json();
        $rows = data_get($json, 'rows', []);

        if (empty($rows)) {
            return [];
        }

        $columnHeaders = data_get($json, 'columnHeaders', []);
        $metricNames = collect($columnHeaders)->pluck('name')->toArray();
        $values = data_get($rows, '0', []);

        $metrics = [];

        foreach ($metricNames as $index => $name) {
            $value = data_get($values, $index, 0);

            $label = match ($name) {
                'views' => 'Views',
                'estimatedMinutesWatched' => 'Minutes Watched',
                'averageViewDuration' => 'Avg. View Duration (s)',
                'averageViewPercentage' => 'Avg. View Percentage',
                'subscribersGained' => 'Subscribers Gained',
                'subscribersLost' => 'Subscribers Lost',
                'likes' => 'Likes',
                default => ucfirst(str_replace('_', ' ', $name)),
            };

            $metrics[] = ['label' => $label, 'value' => round((float) $value, 1)];
        }

        return $metrics;
    }

    private function getHttpClient(): PendingRequest
    {
        return $this->socialHttp()->withToken($this->accessToken);
    }

    private function refreshToken(SocialAccount $account): void
    {
        if (! $account->refresh_token) {
            throw new TokenExpiredException('No refresh token available for YouTube account');
        }

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'grant_type' => 'refresh_token',
            'refresh_token' => $account->refresh_token,
        ]);

        if ($response->failed()) {
            Log::error('YouTube token refresh failed', ['body' => $this->redactResponseBody($response->body())]);

            throw new TokenExpiredException('Failed to refresh YouTube token');
        }

        $data = $response->json();

        $account->update([
            'access_token' => data_get($data, 'access_token'),
            'refresh_token' => data_get($data, 'refresh_token', $account->refresh_token),
            'token_expires_at' => data_get($data, 'expires_in') ? now()->addSeconds(data_get($data, 'expires_in')) : null,
        ]);
    }
}
