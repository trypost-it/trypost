<?php

declare(strict_types=1);

namespace App\Services\Post;

/**
 * Default UrlSafetyGuard. Rejects:
 *   - non-http(s) schemes
 *   - missing or empty hosts
 *   - literal IPv4/IPv6 hosts in restricted ranges
 *   - DNS hostnames whose A/AAAA records resolve into restricted ranges
 *     (covers DNS-rebinding attempts where the first lookup is public and
 *     subsequent lookups resolve internally)
 */
class DnsUrlSafetyGuard implements UrlSafetyGuard
{
    public function isSafe(string $url): bool
    {
        $parts = parse_url($url);

        if (! is_array($parts) || ! in_array(data_get($parts, 'scheme'), ['http', 'https'], true)) {
            return false;
        }

        $host = data_get($parts, 'host');

        if (! is_string($host) || $host === '') {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return $this->ipIsPublic($host);
        }

        $records = @dns_get_record($host, DNS_A | DNS_AAAA);

        if ($records === false || $records === []) {
            return false;
        }

        foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;
            if (! is_string($ip) || ! $this->ipIsPublic($ip)) {
                return false;
            }
        }

        return true;
    }

    private function ipIsPublic(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;
    }
}
