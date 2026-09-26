<?php

declare(strict_types=1);

use App\Models\User;
use Mortelos\Ui\Testing\LivewireMonitor;
use Pest\Browser\Api\AwaitableWebpage;
use Pest\Browser\Api\PendingAwaitablePage;

const USERS_PANEL_GONE = "document.querySelector('[data-access-panel]') === null";
const USERS_CLOSE_CLICK = "() => { document.querySelector('[data-access-close]').click(); return true; }";

it('opens and closes the access panel with island-only round trips', function (): void {
    $page = verifyAsAdmin('/users');

    LivewireMonitor::expect($page, fn (PendingAwaitablePage|AwaitableWebpage $page) => $page, 0);
    $page->script("() => { document.querySelector('h1').__verifyProbe = 1; return true; }");

    LivewireMonitor::expect($page, fn (PendingAwaitablePage|AwaitableWebpage $page) => $page->click('Bekijk toegang'), 1, 'access');
    $page->assertSeeIn('[data-access-panel]', 'admin@example.test');

    // The close button covers the whole overlay and the panel sits on top of its centre,
    // so a pointer click would hit the panel; a DOM click reaches the button itself.
    LivewireMonitor::expect($page, fn (PendingAwaitablePage|AwaitableWebpage $page) => $page->script(USERS_CLOSE_CLICK), 1, 'access');
    $page->assertScript(USERS_PANEL_GONE);

    LivewireMonitor::expect($page, fn (PendingAwaitablePage|AwaitableWebpage $page) => $page->click('Bekijk toegang'), 1, 'access');
    LivewireMonitor::expect($page, fn (PendingAwaitablePage|AwaitableWebpage $page) => $page->keys('html > body', 'Escape'), 1, 'access');
    $page->assertScript(USERS_PANEL_GONE)
        ->assertScript("document.querySelector('h1').__verifyProbe === 1");
});

it('shows the second user after closing the first', function (): void {
    $member = User::factory()->create(['email' => 'lid@example.test']);
    $member->tenants()->attach(config()->string('starter.tenancy.default_tenant_id'), ['role' => 'member', 'role_id' => null]);

    $page = verifyAsAdmin('/users');

    $page->click('tr:has-text("admin@example.test") >> text=Bekijk toegang')
        ->assertSeeIn('[data-access-panel]', 'admin@example.test')
        ->keys('html > body', 'Escape')
        ->assertScript(USERS_PANEL_GONE);

    LivewireMonitor::expect($page, fn (PendingAwaitablePage|AwaitableWebpage $page) => $page
        ->click('tr:has-text("lid@example.test") >> text=Bekijk toegang'), 1, 'access');

    $page->assertSeeIn('[data-access-panel]', 'lid@example.test')
        ->assertDontSeeIn('[data-access-panel]', 'admin@example.test');
});
