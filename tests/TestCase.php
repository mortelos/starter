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
}
