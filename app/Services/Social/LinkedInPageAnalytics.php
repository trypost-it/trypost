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

class LinkedInPageAnalytics
{
    use HasSocialHttpClient;

    private string $baseUrl = 'https://api.linkedin.com/v2';

    private string $accessToken;

    public function getMetrics(SocialAccount $account, ?CarbonInterface $since = null, ?CarbonInterface $until = null): array
    {
        $since ??= now()->subDays(7);
        $until ??= now();

        $cacheKey = "analytics:linkedin-page:{$account->id}:{$since->format('Y-m-d')}:{$until->format('Y-m-d')}";
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

        $orgUrn = urlencode("urn:li:organization:{$account->platform_user_id}");
        $startMs = $since->startOfDay()->getTimestampMs();
        $endMs = $until->endOfDay()->getTimestampMs();
        $timeInterval = "(timeRange:(start:{$startMs},end:{$endMs}),timeGranularityType:DAY)";

        $metrics = [];

        // Page statistics (page views)
        $pageStats = $this->fetchPageStatistics($orgUrn, $timeInterval);
        $metrics = array_merge($metrics, $pageStats);

        // Follower statistics
        $followerStats = $this->fetchFollowerStatistics($orgUrn, $timeInterval);
        $metrics = array_merge($metrics, $followerStats);

        // Share statistics (engagement)
        $shareStats = $this->fetchShareStatistics($orgUrn, $timeInterval);
        $metrics = array_merge($metrics, $shareStats);

        return $metrics;
    }

    private function fetchPageStatistics(string $orgUrn, string $timeInterval): array
    {
        $response = $this->getHttpClient()
            ->get("{$this->baseUrl}/organizationPageStatistics", [
                'q' => 'organization',
                'organization' => urldecode($orgUrn),
                'timeIntervals' => $timeInterval,
            ]);

        if ($response->failed()) {
            Log::warning('LinkedIn page statistics fetch failed', [
                'body' => $this->redactResponseBody($response->body()),
            ]);

            return [];
        }

        $elements = data_get($response->json(), 'elements', []);
        $totalPageViews = 0;

        foreach ($elements as $element) {
            $totalPageViews += data_get($element, 'totalPageStatistics.views.allPageViews.pageViews', 0);
        }

        return $totalPageViews > 0 ? [['label' => 'Page Views', 'value' => $totalPageViews]] : [];
    }

    private function fetchFollowerStatistics(string $orgUrn, string $timeInterval): array
    {
        $response = $this->getHttpClient()
            ->get("{$this->baseUrl}/organizationalEntityFollowerStatistics", [
                'q' => 'organizationalEntity',
                'organizationalEntity' => urldecode($orgUrn),
                'timeIntervals' => $timeInterval,
            ]);

        if ($response->failed()) {
            Log::warning('LinkedIn follower statistics fetch failed', [
                'body' => $this->redactResponseBody($response->body()),
            ]);

            return [];
        }

        $elements = data_get($response->json(), 'elements', []);
        $organicFollowers = 0;
        $paidFollowers = 0;

        foreach ($elements as $element) {
            $organicFollowers += data_get($element, 'followerGains.organicFollowerGain', 0);
            $paidFollowers += data_get($element, 'followerGains.paidFollowerGain', 0);
        }

        $metrics = [];

        if ($organicFollowers > 0) {
            $metrics[] = ['label' => 'Organic Followers', 'value' => $organicFollowers];
        }

        if ($paidFollowers > 0) {
            $metrics[] = ['label' => 'Paid Followers', 'value' => $paidFollowers];
        }

        return $metrics;
    }

    private function fetchShareStatistics(string $orgUrn, string $timeInterval): array
    {
        $response = $this->getHttpClient()
            ->get("{$this->baseUrl}/organizationalEntityShareStatistics", [
                'q' => 'organizationalEntity',
                'organizationalEntity' => urldecode($orgUrn),
                'timeIntervals' => $timeInterval,
            ]);

        if ($response->failed()) {
            Log::warning('LinkedIn share statistics fetch failed', [
                'body' => $this->redactResponseBody($response->body()),
            ]);

            return [];
        }

        $elements = data_get($response->json(), 'elements', []);
        $totalShares = 0;
        $totalClicks = 0;
        $totalLikes = 0;
        $totalComments = 0;
        $totalImpressions = 0;

        foreach ($elements as $element) {
            $stats = data_get($element, 'totalShareStatistics', []);
            $totalShares += data_get($stats, 'shareCount', 0);
            $totalClicks += data_get($stats, 'clickCount', 0);
            $totalLikes += data_get($stats, 'likeCount', 0);
            $totalComments += data_get($stats, 'commentCount', 0);
            $totalImpressions += data_get($stats, 'impressionCount', 0);
        }

        $metrics = [];

        if ($totalImpressions > 0) {
            $metrics[] = ['label' => 'Impressions', 'value' => $totalImpressions];
        }
        if ($totalClicks > 0) {
            $metrics[] = ['label' => 'Clicks', 'value' => $totalClicks];
        }
        if ($totalLikes > 0) {
            $metrics[] = ['label' => 'Likes', 'value' => $totalLikes];
        }
        if ($totalComments > 0) {
            $metrics[] = ['label' => 'Comments', 'value' => $totalComments];
        }
        if ($totalShares > 0) {
            $metrics[] = ['label' => 'Shares', 'value' => $totalShares];
        }

        return $metrics;
    }

    private function getHttpClient(): PendingRequest
    {
        return $this->socialHttp()->withToken($this->accessToken)
            ->withHeaders([
                'Linkedin-Version' => '202601',
                'X-Restli-Protocol-Version' => '2.0.0',
            ]);
    }

    private function refreshToken(SocialAccount $account): void
    {
        if (! $account->refresh_token) {
            throw new TokenExpiredException('No refresh token available for LinkedIn Page account');
        }

        $response = Http::asForm()->post('https://www.linkedin.com/oauth/v2/accessToken', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $account->refresh_token,
            'client_id' => config('services.linkedin-openid.client_id'),
            'client_secret' => config('services.linkedin-openid.client_secret'),
        ]);

        if ($response->failed()) {
            Log::error('LinkedIn token refresh failed', ['body' => $this->redactResponseBody($response->body())]);
            throw new TokenExpiredException('LinkedIn token refresh failed');
        }

        $data = $response->json();

        $account->update([
            'access_token' => data_get($data, 'access_token'),
            'refresh_token' => data_get($data, 'refresh_token', $account->refresh_token),
            'token_expires_at' => data_get($data, 'expires_in') ? now()->addSeconds(data_get($data, 'expires_in')) : null,
        ]);
    }
}
