<?php

declare(strict_types=1);

namespace App\Services\Social;

use App\Enums\SocialAccount\Platform;
use App\Exceptions\TokenExpiredException;
use App\Models\SocialAccount;
use App\Services\Social\Concerns\HasSocialHttpClient;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InstagramAnalytics
{
    use HasSocialHttpClient;

    private string $baseUrl;

    private string $accessToken;

    public function getMetrics(SocialAccount $account, ?CarbonInterface $since = null, ?CarbonInterface $until = null): array
    {
        $since ??= now()->subDays(7);
        $until ??= now();

        $cacheKey = "analytics:instagram:{$account->id}:{$since->format('Y-m-d')}:{$until->format('Y-m-d')}";
        $cacheTtl = app()->isProduction() ? 3600 : 1;

        return Cache::remember($cacheKey, $cacheTtl, function () use ($account, $since, $until) {
            return $this->fetchMetricsFromApi($account, $since, $until);
        });
    }

    private function fetchMetricsFromApi(SocialAccount $account, CarbonInterface $since, CarbonInterface $until): array
    {
        $this->baseUrl = str_replace('/v24.0', '', $account->platform->instagramGraphBaseUrl());

        if ($account->is_token_expired || $account->is_token_expiring_soon) {
            $this->refreshTokenWithLock($account, fn () => $this->refreshToken($account));
            $account->refresh();
        }

        $this->accessToken = $account->access_token;

        $metrics = [];

        $timeSeriesMetrics = $this->fetchTimeSeriesMetrics($account, $since, $until);
        $metrics = array_merge($metrics, $timeSeriesMetrics);

        $totalValueMetrics = $this->fetchTotalValueMetrics($account, $since, $until);
        $metrics = array_merge($metrics, $totalValueMetrics);

        return $metrics;
    }

    private function fetchTimeSeriesMetrics(SocialAccount $account, CarbonInterface $since, CarbonInterface $until): array
    {
        $response = $this->getHttpClient()
            ->get("{$this->baseUrl}/v21.0/{$account->platform_user_id}/insights", [
                'metric' => 'reach,follower_count',
                'period' => 'day',
                'since' => $since->startOfDay()->unix(),
                'until' => $until->endOfDay()->unix(),
                'access_token' => $this->accessToken,
            ]);

        if ($response->failed()) {
            Log::warning('Instagram insights (time series) fetch failed', [
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
                'reach' => 'Reach',
                'follower_count' => 'Followers',
                default => ucfirst(str_replace('_', ' ', $name)),
            };

            $metrics[] = ['label' => $label, 'value' => $total];
        }

        return $metrics;
    }

    private function fetchTotalValueMetrics(SocialAccount $account, CarbonInterface $since, CarbonInterface $until): array
    {
        $response = $this->getHttpClient()
            ->get("{$this->baseUrl}/v21.0/{$account->platform_user_id}/insights", [
                'metric' => 'likes,comments,shares,saves,views,total_interactions',
                'metric_type' => 'total_value',
                'period' => 'day',
                'since' => $since->startOfDay()->unix(),
                'until' => $until->endOfDay()->unix(),
                'access_token' => $this->accessToken,
            ]);

        if ($response->failed()) {
            Log::warning('Instagram insights (total value) fetch failed', [
                'body' => $this->redactResponseBody($response->body()),
            ]);

            return [];
        }

        $data = data_get($response->json(), 'data', []);
        $metrics = [];

        foreach ($data as $metric) {
            $name = data_get($metric, 'name');
            $value = data_get($metric, 'total_value.value', 0);

            $label = match ($name) {
                'total_interactions' => 'Interactions',
                default => ucfirst(str_replace('_', ' ', $name)),
            };

            $metrics[] = ['label' => $label, 'value' => $value];
        }

        return $metrics;
    }

    private function getHttpClient(): PendingRequest
    {
        return $this->socialHttp();
    }

    private function refreshToken(SocialAccount $account): void
    {
        if ($account->platform === Platform::InstagramFacebook) {
            return;
        }

        if (! $account->refresh_token) {
            throw new TokenExpiredException('No refresh token available for Instagram account');
        }

        $response = Http::get('https://graph.instagram.com/refresh_access_token', [
            'grant_type' => 'ig_refresh_token',
            'access_token' => $account->access_token,
        ]);

        if ($response->failed()) {
            Log::error('Instagram token refresh failed', ['body' => $this->redactResponseBody($response->body())]);
            throw new TokenExpiredException('Instagram token refresh failed');
        }

        $data = $response->json();

        $account->update([
            'access_token' => data_get($data, 'access_token'),
            'token_expires_at' => data_get($data, 'expires_in') ? now()->addSeconds(data_get($data, 'expires_in')) : null,
        ]);
    }
}
