<?php

declare(strict_types=1);

it('shows the empty inbox within the verify budgets', function (): void {
    $page = verifyAsAdmin('/inbox');

    expectNoPostsSinceLoad($page);

    $page->assertSee('Inbox is nog niet ingericht');
});
