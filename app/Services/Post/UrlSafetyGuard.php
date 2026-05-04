<?php

declare(strict_types=1);

namespace App\Services\Post;

/**
 * Decides whether a user-supplied URL is safe to fetch from the server side.
 * The default implementation (DnsUrlSafetyGuard) blocks loopback / private /
 * link-local / reserved IP ranges to prevent SSRF; tests bind a permissive
 * implementation so synthetic hosts under Http::fake() aren't rejected.
 */
interface UrlSafetyGuard
{
    public function isSafe(string $url): bool;
}
