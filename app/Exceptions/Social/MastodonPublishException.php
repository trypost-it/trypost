<?php

declare(strict_types=1);

namespace App\Exceptions\Social;

use App\Exceptions\TokenExpiredException;
use Illuminate\Http\Client\Response;

class MastodonPublishException extends SocialPublishException
{
    public static function fromApiResponse(mixed $response): static
    {
        /** @var Response $response */
        $status = $response->status();
        $body = $response->json();
        $rawResponse = $response->body();

        $errorMessage = data_get($body, 'error', 'An unknown Mastodon error occurred.');

        if ($status === 401) {
            throw new TokenExpiredException(
                message: $errorMessage,
                platformErrorCode: (string) $status,
            );
        }

        if ($status === 403) {
            return new static(
                userMessage: 'This action is not allowed.',
                category: ErrorCategory::Permission,
                platformErrorCode: (string) $status,
                rawResponse: $rawResponse,
            );
        }

        if ($status === 422 && str_contains(strtolower($rawResponse), "text can't be blank")) {
            return new static(
                userMessage: 'Post text is required when no media is attached.',
                category: ErrorCategory::ContentPolicy,
                platformErrorCode: (string) $status,
                rawResponse: $rawResponse,
            );
        }

        if ($status === 422) {
            return new static(
                userMessage: 'Media validation failed.',
                category: ErrorCategory::MediaFormat,
                platformErrorCode: (string) $status,
                rawResponse: $rawResponse,
            );
        }

        if ($status === 413) {
            return new static(
                userMessage: 'File is too large for this Mastodon instance.',
                category: ErrorCategory::MediaFormat,
                platformErrorCode: (string) $status,
                rawResponse: $rawResponse,
            );
        }

        if ($status === 429) {
            return new static(
                userMessage: 'Rate limit exceeded. Please try again later.',
                category: ErrorCategory::RateLimit,
                platformErrorCode: (string) $status,
                rawResponse: $rawResponse,
            );
        }

        if ($status === 503) {
            return new static(
                userMessage: 'Mastodon server error. Please try again.',
                category: ErrorCategory::ServerError,
                platformErrorCode: (string) $status,
                rawResponse: $rawResponse,
            );
        }

        return new static(
            userMessage: $errorMessage,
            category: ErrorCategory::Unknown,
            platformErrorCode: (string) $status,
            rawResponse: $rawResponse,
        );
    }

    public function platform(): string
    {
        return 'mastodon';
    }
}
