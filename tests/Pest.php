<?php

declare(strict_types=1);

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mortelos\Ui\Testing\LivewireMonitor;
use Pest\Browser\Api\AwaitableWebpage;
use Pest\Browser\Api\PendingAwaitablePage;
use Tests\TestCase;

use function Pest\Laravel\seed;
use function Pest\Laravel\withVite;

require __DIR__.'/Support/Governance.php';

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

/**
 * Asserts that loading the page sent no Livewire POST. expect() with an empty interaction
 * lets the page settle and checks the other budgets, but only counts POSTs that start after
 * it is called; summary() counts from page load, so a wire:init or lazy load fails here.
 */
function expectNoPostsSinceLoad(PendingAwaitablePage|AwaitableWebpage $page): void
{
    LivewireMonitor::expect($page, fn (PendingAwaitablePage|AwaitableWebpage $page) => $page, 0);

    $posts = LivewireMonitor::summary($page)['posts'];

    expect($posts)->toBe([], 'Livewire POSTs since page load: '.json_encode($posts));
}
