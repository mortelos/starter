<?php

declare(strict_types=1);

use Mortelos\Ui\Testing\LivewireMonitor;
use Pest\Browser\Api\AwaitableWebpage;
use Pest\Browser\Api\PendingAwaitablePage;

it('opens the dashboard as the seeded admin within the verify budgets', function (): void {
    $page = verifyAsAdmin('/dashboard');

    LivewireMonitor::expect($page, fn (PendingAwaitablePage|AwaitableWebpage $page) => $page, 0);

    $page->assertSee('Management Dashboard');
});
