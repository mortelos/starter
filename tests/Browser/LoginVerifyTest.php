<?php

declare(strict_types=1);

it('opens the dashboard as the seeded admin within the verify budgets', function (): void {
    $page = verifyAsAdmin('/dashboard');

    expectNoPostsSinceLoad($page);

    $page->assertSee('Management Dashboard');
});
