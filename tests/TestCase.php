<?php

declare(strict_types=1);

namespace Tests;

use App\Services\Post\UrlSafetyGuard;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Indicates whether the default seeder should run before each test.
     *
     * @var bool
     */
    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        // Bypass the DNS-based SSRF guard during tests. Feature tests use
        // Http::fake() with synthetic hosts (e.g. cdn.example.com) that
        // wouldn't resolve to public IPs; the real guard is exercised by
        // tests/Unit/Services/Post/DnsUrlSafetyGuardTest in isolation.
        $this->app->bind(UrlSafetyGuard::class, fn () => new class implements UrlSafetyGuard
        {
            public function isSafe(string $url): bool
            {
                return true;
            }
        });
    }
}
