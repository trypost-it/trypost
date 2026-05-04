<?php

declare(strict_types=1);

use App\Services\Post\DnsUrlSafetyGuard;

beforeEach(function () {
    // The TestCase setUp binds a permissive UrlSafetyGuard for feature
    // tests; this unit test exercises the real DnsUrlSafetyGuard directly,
    // so we instantiate it instead of resolving from the container.
    $this->guard = new DnsUrlSafetyGuard;
});

test('rejects non-http(s) schemes', function () {
    expect($this->guard->isSafe('ftp://example.com/file.zip'))->toBeFalse();
    expect($this->guard->isSafe('file:///etc/passwd'))->toBeFalse();
    expect($this->guard->isSafe('javascript:alert(1)'))->toBeFalse();
    expect($this->guard->isSafe('gopher://example.com'))->toBeFalse();
});

test('rejects malformed URLs', function () {
    expect($this->guard->isSafe('not-a-url'))->toBeFalse();
    expect($this->guard->isSafe('http://'))->toBeFalse();
    expect($this->guard->isSafe(''))->toBeFalse();
});

test('rejects IPv4 hosts in restricted ranges', function () {
    // Loopback
    expect($this->guard->isSafe('http://127.0.0.1/'))->toBeFalse();
    expect($this->guard->isSafe('http://127.255.255.254/'))->toBeFalse();

    // RFC1918 private
    expect($this->guard->isSafe('http://10.0.0.1/'))->toBeFalse();
    expect($this->guard->isSafe('http://172.16.0.1/'))->toBeFalse();
    expect($this->guard->isSafe('http://192.168.1.1/'))->toBeFalse();

    // Link-local + AWS metadata endpoint
    expect($this->guard->isSafe('http://169.254.169.254/latest/meta-data'))->toBeFalse();

    // Reserved zero / broadcast
    expect($this->guard->isSafe('http://0.0.0.0/'))->toBeFalse();
    expect($this->guard->isSafe('http://255.255.255.255/'))->toBeFalse();
});

test('rejects IPv6 hosts in restricted ranges', function () {
    // Loopback
    expect($this->guard->isSafe('http://[::1]/'))->toBeFalse();

    // Unique local (fc00::/7)
    expect($this->guard->isSafe('http://[fd00::1]/'))->toBeFalse();

    // Link-local (fe80::/10)
    expect($this->guard->isSafe('http://[fe80::1]/'))->toBeFalse();
});

test('accepts a literal public IPv4', function () {
    // 1.1.1.1 (Cloudflare DNS) is a stable public IP we can hard-code.
    expect($this->guard->isSafe('http://1.1.1.1/'))->toBeTrue();
    expect($this->guard->isSafe('https://8.8.8.8/'))->toBeTrue();
});

test('accepts a hostname that resolves to public IPs', function () {
    // example.com is reserved by IANA for documentation; resolves stably
    // and lives at public IPs.
    expect($this->guard->isSafe('https://example.com/'))->toBeTrue();
})->skip(getenv('CI') === 'true', 'depends on outbound DNS, skipped on CI');

test('rejects a hostname with no DNS records', function () {
    // .invalid is reserved (RFC 2606) — guaranteed not to resolve.
    expect($this->guard->isSafe('http://does-not-exist.invalid/'))->toBeFalse();
})->skip(getenv('CI') === 'true', 'depends on DNS resolution behavior, skipped on CI');
