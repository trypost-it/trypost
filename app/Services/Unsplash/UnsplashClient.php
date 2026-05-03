<?php

declare(strict_types=1);

namespace App\Services\Unsplash;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UnsplashClient
{
    /**
     * Generic, always-available fallback keywords. We try these last so a slide
     * never ends up without a photo.
     */
    private const array FALLBACK_KEYWORDS = ['business', 'workspace', 'abstract', 'background'];

    /**
     * Search a single photo with progressive fallbacks so a slide never returns
     * without a photo:
     *   1. all keywords + color filter
     *   2. all keywords, no color
     *   3. only the first keyword, no color
     *   4. each generic fallback keyword in turn
     *
     * @param  array<int, string>  $keywords
     * @return array{id: string, url: string, alt_description: ?string}|null
     */
    public function searchPhoto(array $keywords, string $orientation = 'portrait', ?string $colorBucket = null): ?array
    {
        $key = config('services.unsplash.access_key');
        if (! $key) {
            Log::warning('Unsplash access key not configured');

            return null;
        }

        $cleanKeywords = array_values(array_filter(array_map('trim', $keywords)));

        if (empty($cleanKeywords)) {
            return $this->tryFallbacks([], $orientation, $key);
        }

        $query = implode(' ', $cleanKeywords);

        // 1. all keywords + color
        $photo = $this->fetchOne($key, $query, $orientation, $colorBucket);
        if ($photo) {
            return $photo;
        }

        // 2. all keywords, no color
        if ($colorBucket !== null) {
            $photo = $this->fetchOne($key, $query, $orientation, null);
            if ($photo) {
                return $photo;
            }
        }

        // 3. only the first keyword
        if (count($cleanKeywords) > 1) {
            $photo = $this->fetchOne($key, $cleanKeywords[0], $orientation, null);
            if ($photo) {
                return $photo;
            }
        }

        // 4. generic fallbacks
        return $this->tryFallbacks($cleanKeywords, $orientation, $key);
    }

    /**
     * @return array{id: string, url: string, alt_description: ?string}|null
     */
    private function fetchOne(string $key, string $query, string $orientation, ?string $colorBucket): ?array
    {
        if ($query === '') {
            return null;
        }

        $params = [
            'query' => $query,
            'orientation' => $orientation,
            'per_page' => 1,
        ];
        if ($colorBucket) {
            $params['color'] = $colorBucket;
        }

        $cacheKey = 'unsplash:'.md5(json_encode($params));

        $result = Cache::get($cacheKey);

        if ($result === null) {
            $response = Http::withHeaders(['Authorization' => 'Client-ID '.$key])
                ->timeout(10)
                ->get('https://api.unsplash.com/search/photos', $params);

            if (! $response->successful()) {
                Log::warning('Unsplash search failed', ['status' => $response->status(), 'query' => $query]);

                return null;
            }

            $result = $response->json();

            // Only cache successful responses that actually returned results, so
            // a transient empty hit doesn't get pinned for an hour.
            if (! empty(data_get($result, 'results'))) {
                Cache::put($cacheKey, $result, now()->addHour());
            }
        }

        $first = data_get($result, 'results.0');
        if (! $first) {
            return null;
        }

        return [
            'id' => data_get($first, 'id'),
            'url' => data_get($first, 'urls.regular'),
            'alt_description' => data_get($first, 'alt_description'),
        ];
    }

    /**
     * Try each generic fallback keyword in turn.
     *
     * @param  array<int, string>  $skip  fallback keywords to skip (already tried)
     * @return array{id: string, url: string, alt_description: ?string}|null
     */
    private function tryFallbacks(array $skip, string $orientation, string $key): ?array
    {
        foreach (self::FALLBACK_KEYWORDS as $keyword) {
            if (in_array($keyword, $skip, true)) {
                continue;
            }

            $photo = $this->fetchOne($key, $keyword, $orientation, null);
            if ($photo) {
                Log::info('Unsplash fell back to generic keyword', ['keyword' => $keyword]);

                return $photo;
            }
        }

        Log::warning('Unsplash exhausted all fallbacks');

        return null;
    }
}
