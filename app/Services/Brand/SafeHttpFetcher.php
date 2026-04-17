<?php

declare(strict_types=1);

namespace App\Services\Brand;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * HTTP fetcher with SSRF protection, timeout, redirect cap and a branded user-agent.
 * All outbound requests to user-supplied URLs must go through here.
 */
final class SafeHttpFetcher
{
    private const string USER_AGENT = 'TryPostBot/1.0 (+https://trypost.it)';

    private const int TIMEOUT_SECONDS = 10;

    private const int MAX_REDIRECTS = 3;

    public function normalizeUrl(string $url): string
    {
        $url = trim($url);

        if (preg_match('~^[a-z][a-z0-9+.-]*://~i', $url) === 1) {
            return $url;
        }

        return 'https://'.$url;
    }

    public function get(string $url): Response
    {
        $this->guardAgainstSsrf($url);

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->withUserAgent(self::USER_AGENT)
                ->withOptions(['allow_redirects' => ['max' => self::MAX_REDIRECTS]])
                ->get($url);
        } catch (ConnectionException $e) {
            throw new RuntimeException(__('workspaces.create.autofill_errors.unreachable', ['reason' => $e->getMessage()]));
        }

        if ($response->failed()) {
            throw new RuntimeException(__('workspaces.create.autofill_errors.http_status', ['status' => $response->status()]));
        }

        return $response;
    }

    /**
     * Same as get() but never throws — returns null on any failure. Used for logo
     * downloads and other opportunistic fetches where failure is not fatal.
     */
    public function tryGet(string $url): ?Response
    {
        try {
            return $this->get($url);
        } catch (RuntimeException) {
            return null;
        }
    }

    public function guardAgainstSsrf(string $url): void
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) data_get($parts, 'scheme', ''));

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new RuntimeException(__('workspaces.create.autofill_errors.invalid_scheme'));
        }

        $host = (string) data_get($parts, 'host', '');

        if ($host === '') {
            throw new RuntimeException(__('workspaces.create.autofill_errors.missing_host'));
        }

        $ip = gethostbyname($host);

        if ($ip === $host && filter_var($host, FILTER_VALIDATE_IP) === false) {
            throw new RuntimeException(__('workspaces.create.autofill_errors.unresolvable_host', ['host' => $host]));
        }

        $isPublic = filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        );

        if ($isPublic === false) {
            throw new RuntimeException(__('workspaces.create.autofill_errors.private_network'));
        }
    }
}
