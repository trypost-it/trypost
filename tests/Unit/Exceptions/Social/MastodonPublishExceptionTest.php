<?php

declare(strict_types=1);

use App\Exceptions\Social\ErrorCategory;
use App\Exceptions\Social\MastodonPublishException;
use App\Exceptions\TokenExpiredException;
use Illuminate\Support\Facades\Http;

test('HTTP 401 throws TokenExpiredException', function () {
    $response = Http::response(['error' => 'The access token is invalid'], 401);
    $fakeResponse = Http::fake(['*' => $response])->post('https://mastodon.social/api/v1/statuses');

    MastodonPublishException::fromApiResponse($fakeResponse);
})->throws(TokenExpiredException::class);

test('HTTP 403 maps to Permission category', function () {
    $response = Http::response(['error' => 'This action is not allowed'], 403);
    $fakeResponse = Http::fake(['*' => $response])->post('https://mastodon.social/api/v1/statuses');

    $exception = MastodonPublishException::fromApiResponse($fakeResponse);

    expect($exception->category)->toBe(ErrorCategory::Permission)
        ->and($exception->userMessage)->toBe('This action is not allowed.');
});

test('HTTP 422 with text can\'t be blank maps to ContentPolicy category', function () {
    $response = Http::response(['error' => "text can't be blank"], 422);
    $fakeResponse = Http::fake(['*' => $response])->post('https://mastodon.social/api/v1/statuses');

    $exception = MastodonPublishException::fromApiResponse($fakeResponse);

    expect($exception->category)->toBe(ErrorCategory::ContentPolicy)
        ->and($exception->userMessage)->toBe('Post text is required when no media is attached.');
});

test('HTTP 413 maps to MediaFormat category', function () {
    $response = Http::response(['error' => 'File too large'], 413);
    $fakeResponse = Http::fake(['*' => $response])->post('https://mastodon.social/api/v1/statuses');

    $exception = MastodonPublishException::fromApiResponse($fakeResponse);

    expect($exception->category)->toBe(ErrorCategory::MediaFormat)
        ->and($exception->userMessage)->toBe('File is too large for this Mastodon instance.');
});

test('HTTP 429 maps to RateLimit category', function () {
    $response = Http::response(['error' => 'Too many requests'], 429);
    $fakeResponse = Http::fake(['*' => $response])->post('https://mastodon.social/api/v1/statuses');

    $exception = MastodonPublishException::fromApiResponse($fakeResponse);

    expect($exception->category)->toBe(ErrorCategory::RateLimit)
        ->and($exception->userMessage)->toBe('Rate limit exceeded. Please try again later.');
});

test('HTTP 422 without text blank maps to MediaFormat category', function () {
    $response = Http::response(['error' => 'Validation failed'], 422);
    $fakeResponse = Http::fake(['*' => $response])->post('https://mastodon.social/api/v1/statuses');

    $exception = MastodonPublishException::fromApiResponse($fakeResponse);

    expect($exception->category)->toBe(ErrorCategory::MediaFormat)
        ->and($exception->userMessage)->toBe('Media validation failed.');
});

test('unknown status maps to Unknown category with error message', function () {
    $response = Http::response(['error' => 'Something went wrong'], 400);
    $fakeResponse = Http::fake(['*' => $response])->post('https://mastodon.social/api/v1/statuses');

    $exception = MastodonPublishException::fromApiResponse($fakeResponse);

    expect($exception->category)->toBe(ErrorCategory::Unknown)
        ->and($exception->userMessage)->toBe('Something went wrong');
});

test('platform returns mastodon', function () {
    $response = Http::response(['error' => 'Some error'], 400);
    $fakeResponse = Http::fake(['*' => $response])->post('https://mastodon.social/api/v1/statuses');

    $exception = MastodonPublishException::fromApiResponse($fakeResponse);

    expect($exception->platform())->toBe('mastodon');
});
