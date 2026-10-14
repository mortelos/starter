<?php

declare(strict_types=1);

it('shows the onboarding empty state within the verify budgets when no resolver is bound', function (): void {
    $page = verifyAsAdmin('/onboarding');

    expectNoPostsSinceLoad($page);

    $page->assertSee('Onboarding is nog niet ingericht')
        ->assertSee('starter.onboarding.resolver');
});
