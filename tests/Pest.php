<?php

declare(strict_types=1);

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mortelos\Ui\Testing\LivewireMonitor;
use Pest\Browser\Api\PendingAwaitablePage;
use Tests\TestCase;

use function Pest\Laravel\seed;
use function Pest\Laravel\withVite;

uses(TestCase::class)->in('Feature');

uses(TestCase::class, RefreshDatabase::class)->in('Feature/Database');

// Verify tests measure layout shift, so they need the real CSS: undo the
// withoutVite() from TestCase and run `npm run build` before this suite.
uses(TestCase::class, RefreshDatabase::class)
    ->beforeEach(function (): void {
        withVite();
        seed(DatabaseSeeder::class);
    })
    ->in('Browser');

/**
 * Logs in as the seeded admin with the Livewire monitor active from the login page on.
 * The monitor is an init script on the browser context: it survives the login redirect
 * and every later navigation, and each page load starts a fresh count.
 */
function verifyAsAdmin(string $path): PendingAwaitablePage
{
    $page = LivewireMonitor::visit('/login');

    $page->fill('email', 'admin@example.test')
        ->fill('password', 'password')
        ->click('button[type="submit"]')
        ->assertPathIs('/dashboard');

    if ($path !== '/dashboard') {
        $page->navigate($path);
    }

    return $page;
}
