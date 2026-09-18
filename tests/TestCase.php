<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        config(['inertia.ssr.enabled' => false]);

        // php artisan test boots Laravel before PHPUnit env vars apply,
        // so an empty PADDLE_API_KEY from .env would otherwise throw
        // before Http::fake() can intercept Cashier.
        if (blank(config('cashier.api_key'))) {
            config(['cashier.api_key' => 'test_key']);
        }
    }
}
