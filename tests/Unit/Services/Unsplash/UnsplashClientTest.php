<?php

declare(strict_types=1);

use App\Services\Unsplash\UnsplashClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.unsplash.access_key', 'test-key');
    Cache::flush();
});

test('searchPhoto returns null when access key missing', function () {
    config()->set('services.unsplash.access_key', null);

    expect((new UnsplashClient)->searchPhoto(['kitchen']))->toBeNull();
});

test('searchPhoto returns null when keywords are empty and no fallback found', function () {
    Http::fake(['api.unsplash.com/*' => Http::response(['results' => []])]);

    expect((new UnsplashClient)->searchPhoto([]))->toBeNull();
});

test('searchPhoto returns formatted photo on success', function () {
    Http::fake(['api.unsplash.com/*' => Http::response([
        'results' => [[
            'id' => 'abc',
            'urls' => ['regular' => 'https://images.unsplash.com/abc'],
            'alt_description' => 'a kitchen',
        ]],
    ])]);

    $result = (new UnsplashClient)->searchPhoto(['kitchen']);

    expect($result)->toMatchArray([
        'id' => 'abc',
        'url' => 'https://images.unsplash.com/abc',
        'alt_description' => 'a kitchen',
    ]);
});

test('searchPhoto returns null when Unsplash API returns failure status', function () {
    Http::fake(['api.unsplash.com/*' => Http::response([], 500)]);

    $result = (new UnsplashClient)->searchPhoto(['kitchen']);

    expect($result)->toBeNull();
});

test('searchPhoto falls back when color search returns empty results', function () {
    Http::fake([
        'api.unsplash.com/search/photos*' => Http::sequence()
            ->push(['results' => []])
            ->push([
                'results' => [[
                    'id' => 'fallback',
                    'urls' => ['regular' => 'https://images.unsplash.com/fallback'],
                    'alt_description' => null,
                ]],
            ]),
    ]);

    $result = (new UnsplashClient)->searchPhoto(['kitchen'], 'portrait', 'blue');

    expect($result)->not->toBeNull();
    expect($result['id'])->toBe('fallback');
});

test('searchPhoto caches results to avoid duplicate requests', function () {
    Http::fake(['api.unsplash.com/*' => Http::response([
        'results' => [[
            'id' => 'cached',
            'urls' => ['regular' => 'https://images.unsplash.com/cached'],
            'alt_description' => null,
        ]],
    ])]);

    $client = new UnsplashClient;
    $first = $client->searchPhoto(['coffee']);
    $second = $client->searchPhoto(['coffee']);

    expect($first)->toMatchArray($second);
    Http::assertSentCount(1);
});

test('searchPhoto sends color parameter when colorBucket is provided', function () {
    Http::fake(['api.unsplash.com/*' => Http::response([
        'results' => [[
            'id' => 'colored',
            'urls' => ['regular' => 'https://images.unsplash.com/colored'],
            'alt_description' => null,
        ]],
    ])]);

    (new UnsplashClient)->searchPhoto(['office'], 'portrait', 'blue');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'color=blue'));
});
