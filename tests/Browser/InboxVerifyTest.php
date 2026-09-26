<?php

declare(strict_types=1);

use Mortelos\Ui\Testing\LivewireMonitor;
use Pest\Browser\Api\AwaitableWebpage;
use Pest\Browser\Api\PendingAwaitablePage;

it('shows the empty inbox within the verify budgets', function (): void {
    $page = verifyAsAdmin('/inbox');

    LivewireMonitor::expect($page, fn (PendingAwaitablePage|AwaitableWebpage $page) => $page, 0);

    $page->assertSee('Inbox is nog niet ingericht');
});
