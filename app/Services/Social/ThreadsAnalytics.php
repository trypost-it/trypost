<?php

declare(strict_types=1);

namespace App\Services\Social;

use App\Exceptions\TokenExpiredException;
use App\Models\SocialAccount;
use App\Services\Social\Concerns\HasSocialHttpClient;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ThreadsAnalytics
{
    use HasSocialHttpClient;

    private string $baseUrl = 'https://graph.threads.net/v1.0';

    private string $accessToken;

    public function getMetrics(SocialAccount $account, ?CarbonInterface $since = null, ?CarbonInterface $until = null): array
    {
        $since ??= now()->subDays(7);
        $until ??= now();

        $cacheKey = "analytics:threads:{$account->id}:{$since->format('Y-m-d')}:{$until->format('Y-m-d')}";
        $cacheTtl = app()->isProduction() ? 3600 : 1;

        return Cache::remember($cacheKey, $cacheTtl, function () use ($account, $since, $until) {
            return $this->fetchMetricsFromApi($account, $since, $until);
        });
    }

    private function fetchMetricsFromApi(SocialAccount $account, CarbonInterface $since, CarbonInterface $until): array
    {
        if ($account->is_token_expired || $account->is_token_expiring_soon) {
            $this->refreshTokenWithLock($account, fn () => $this->refreshToken($account));
            $account->refresh();
        }

        $this->accessToken = $account->access_token;

        $response = $this->getHttpClient()
            ->get("{$this->baseUrl}/{$account->platform_user_id}/threads_insights", [
                'metric' => 'views,likes,replies,reposts,quotes',
                'period' => 'day',
                'since' => $since->startOfDay()->unix(),
                'until' => $until->endOfDay()->unix(),
                'access_token' => $this->accessToken,
            ]);

        if ($response->failed()) {
            Log::warning('Threads insights fetch failed', [
                'body' => $this->redactResponseBody($response->body()),
            ]);

            return [];
        }

        $data = data_get($response->json(), 'data', []);
        $metrics = [];

        foreach ($data as $metric) {
            $name = data_get($metric, 'name');

            // Some metrics return total_value, others return values array
            $totalValue = data_get($metric, 'total_value.value');
            if ($totalValue !== null) {
                $value = $totalValue;
            } else {
                $values = data_get($metric, 'values', []);
                $value = collect($values)->sum('value');
            }

            $label = ucfirst(str_replace('_', ' ', $name));

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
        $response = Http::get('https://graph.threads.net/refresh_access_token', [
            'grant_type' => 'th_refresh_token',
            'access_token' => $account->access_token,
        ]);

        if ($response->failed()) {
            Log::error('Threads token refresh failed', ['body' => $this->redactResponseBody($response->body())]);
            throw new TokenExpiredException('Threads token refresh failed');
        }

        $data = $response->json();

        $account->update([
            'access_token' => data_get($data, 'access_token'),
            'token_expires_at' => data_get($data, 'expires_in') ? now()->addSeconds(data_get($data, 'expires_in')) : null,
        ]);
    }
}
