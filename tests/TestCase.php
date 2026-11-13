<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Smoke/boot tests assert that pages render, not that the frontend bundle
        // is built; without this they 500 on a missing Vite manifest in any env
        // where `npm run build` has not run (e.g. CI without a Node step).
        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        // mortelos/framework (0.7.84 too) locks every tenant creation (Tenancy\TenantCreationLock: a file on SQLite, an
        // advisory lock on PostgreSQL) and lets go of it when the application terminates. A test that does not make a
        // request never terminates its application, and every test boots a new one in the same process, so the lock
        // would stay and the next test that creates a tenant would fail ("tenant creation is locked").
        $this->app->terminate();

        parent::tearDown();
    }
}
