<?php

declare(strict_types=1);

namespace App\Services\Social\Concerns;

use App\Models\PostPlatform;
use App\Models\SocialAccount;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

trait HasSocialHttpClient
{
    protected function validateContentLength(PostPlatform $postPlatform): void
    {
        $maxLength = $postPlatform->platform->maxContentLength();
        $contentLength = mb_strlen($postPlatform->content ?? '');

        if ($contentLength > $maxLength) {
            throw new \Exception(
                "Content exceeds {$postPlatform->platform->label()} limit of {$maxLength} characters ({$contentLength} provided)."
            );
        }
    }

    protected function refreshTokenWithLock(SocialAccount $account, callable $refreshFn): void
    {
        $lock = Cache::lock("token_refresh:{$account->id}", 30);

        if (! $lock->get()) {
            // Another process is refreshing, wait and reload
            sleep(2);
            $account->refresh();

            return;
        }

        try {
            $refreshFn();
        } finally {
            $lock->release();
        }
    }

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
