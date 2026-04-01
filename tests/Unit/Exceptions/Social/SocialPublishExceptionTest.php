<?php

declare(strict_types=1);

use App\Exceptions\Social\ErrorCategory;
use App\Exceptions\Social\SocialPublishException;

// Concrete implementation for testing the abstract class
class TestPlatformException extends SocialPublishException
{
    public static function fromApiResponse(mixed $response): static
    {
        return new static(
            userMessage: 'Test error',
            category: ErrorCategory::Unknown,
            platformErrorCode: null,
            rawResponse: json_encode($response),
        );
    }

    public function platform(): string
    {
        return 'test_platform';
    }
}

test('context returns correct data', function () {
    $exception = new TestPlatformException(
        userMessage: 'Your image is too large for Instagram.',
        category: ErrorCategory::MediaFormat,
        platformErrorCode: 'MEDIA_TOO_LARGE',
        rawResponse: '{"error": "image too large"}',
    );

    expect($exception->context())->toBe([
        'platform' => 'test_platform',
        'category' => 'media_format',
        'platform_error_code' => 'MEDIA_TOO_LARGE',
        'user_message' => 'Your image is too large for Instagram.',
        'raw_response' => '{"error": "image too large"}',
    ]);
});

test('context returns null for optional fields when not provided', function () {
    $exception = new TestPlatformException(
        userMessage: 'Something went wrong.',
        category: ErrorCategory::Unknown,
    );

    expect($exception->context())->toBe([
        'platform' => 'test_platform',
        'category' => 'unknown',
        'platform_error_code' => null,
        'user_message' => 'Something went wrong.',
        'raw_response' => null,
    ]);
});

test('exception message matches user message', function () {
    $exception = new TestPlatformException(
        userMessage: 'Rate limit exceeded.',
        category: ErrorCategory::RateLimit,
    );

    expect($exception->getMessage())->toBe('Rate limit exceeded.');
});

test('fromApiResponse creates exception from response', function () {
    $exception = TestPlatformException::fromApiResponse(['error' => 'test']);

    expect($exception)
        ->toBeInstanceOf(SocialPublishException::class)
        ->and($exception->userMessage)->toBe('Test error')
        ->and($exception->category)->toBe(ErrorCategory::Unknown)
        ->and($exception->rawResponse)->toBe('{"error":"test"}');
});

test('error category enum has all expected cases', function () {
    expect(ErrorCategory::cases())->toHaveCount(6)
        ->and(ErrorCategory::MediaFormat->value)->toBe('media_format')
        ->and(ErrorCategory::RateLimit->value)->toBe('rate_limit')
        ->and(ErrorCategory::Permission->value)->toBe('permission')
        ->and(ErrorCategory::ContentPolicy->value)->toBe('content_policy')
        ->and(ErrorCategory::ServerError->value)->toBe('server_error')
        ->and(ErrorCategory::Unknown->value)->toBe('unknown');
});
