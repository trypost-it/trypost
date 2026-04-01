<?php

declare(strict_types=1);

namespace App\Exceptions\Social;

use RuntimeException;

abstract class SocialPublishException extends RuntimeException
{
    public function __construct(
        public readonly string $userMessage,
        public readonly ErrorCategory $category,
        public readonly ?string $platformErrorCode = null,
        public readonly ?string $rawResponse = null,
    ) {
        parent::__construct($userMessage);
    }

    /**
     * @return array{platform: string, category: string, platform_error_code: ?string, user_message: string, raw_response: ?string}
     */
    public function context(): array
    {
        return [
            'platform' => static::platform(),
            'category' => $this->category->value,
            'platform_error_code' => $this->platformErrorCode,
            'user_message' => $this->userMessage,
            'raw_response' => $this->redactTokens($this->rawResponse),
        ];
    }

    private function redactTokens(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        // Redact common token patterns from API error responses
        return preg_replace(
            [
                '/access_token=([^&"\s]+)/',
                '/"access_token"\s*:\s*"([^"]+)"/',
                '/Bearer\s+\S+/',
            ],
            [
                'access_token=[REDACTED]',
                '"access_token":"[REDACTED]"',
                'Bearer [REDACTED]',
            ],
            $text
        );
    }

    abstract public static function fromApiResponse(mixed $response): static;

    abstract public function platform(): string;
}
