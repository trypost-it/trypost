<?php

declare(strict_types=1);

namespace App\Services\Social\Concerns;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

trait HasSocialHttpClient
{
    protected function socialHttp(): PendingRequest
    {
        return Http::retry(
            times: 3,
            sleepMilliseconds: 5000,
            when: fn ($exception, $request) => $exception->response?->status() === 429,
            throw: false,
        )->timeout(120);
    }

    protected function redactResponseBody(string $body): string
    {
        return preg_replace(
            [
                '/access_token=([^&"\s]+)/',
                '/"access_token"\s*:\s*"([^"]+)"/',
                '/Bearer\s+\S+/',
                '/"token"\s*:\s*"([^"]+)"/',
            ],
            [
                'access_token=[REDACTED]',
                '"access_token":"[REDACTED]"',
                'Bearer [REDACTED]',
                '"token":"[REDACTED]"',
            ],
            $body
        );
    }
}
