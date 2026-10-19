<?php

declare(strict_types=1);

use Mortelos\Ui\Testing\LivewireMonitor;

it('opens the dashboard as the seeded admin within the verify budgets', function (): void {
    $page = verifyAsAdmin('/dashboard');

    expectNoPostsSinceLoad($page);

    $page->assertSee('Management Dashboard');
    fwrite(STDERR, 'LivewireSummary '.json_encode(LivewireMonitor::summary($page), JSON_THROW_ON_ERROR).PHP_EOL);
});
