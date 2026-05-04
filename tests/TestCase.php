<?php

declare(strict_types=1);

namespace Tests;

use App\Services\Post\MediaAttacher;
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

        // Bypass the SSRF check during tests so Http::fake() with
        // synthetic hosts like cdn.example.com isn't rejected.
        MediaAttacher::fakeUrlSafety();
    }
}
