<?php

declare(strict_types=1);

use Mortelos\Ui\Testing\LivewireMonitor;
use Pest\Browser\Api\AwaitableWebpage;
use Pest\Browser\Api\PendingAwaitablePage;
use Tests\Browser\Support\NavigationFixture;

const SEARCH_IS_OPEN = "document.querySelector('[role=combobox]').checkVisibility()";
const SEARCH_INPUT = '[aria-label="Zoek of stel een vraag"]';

beforeEach(function (): void {
    config([
        'starter.layout.sidebar_nav_component' => 'starter::shared.sidebar-nav',
        'starter.layout.universal_search_component' => 'starter::shared.universal-search',
        'starter.navigation.sidebar_resolver' => NavigationFixture::class,
    ]);
});

it('opens search from the sidebar without a server round trip', function (): void {
    $page = verifyAsAdmin('/dashboard');

    LivewireMonitor::expect($page, fn (PendingAwaitablePage|AwaitableWebpage $page) => $page, 0);
    LivewireMonitor::expect($page, fn (PendingAwaitablePage|AwaitableWebpage $page) => $page->click('Zoeken'), 0);

    $page->assertScript(SEARCH_IS_OPEN);
});

it('sends nothing when Escape or an outside click hits a closed search', function (): void {
    $page = verifyAsAdmin('/dashboard');

    LivewireMonitor::expect($page, fn (PendingAwaitablePage|AwaitableWebpage $page) => $page
        ->keys('html > body', ['Escape', 'Escape', 'Escape']), 0);

    LivewireMonitor::expect($page, fn (PendingAwaitablePage|AwaitableWebpage $page) => $page
        ->click('h1 >> nth=0')
        ->click('h1 >> nth=0')
        ->click('h1 >> nth=0'), 0);
});

it('resets the server query once when search closes after a query, and not again', function (): void {
    $page = verifyAsAdmin('/dashboard');

    $page->click('Zoeken')->assertScript(SEARCH_IS_OPEN);
    LivewireMonitor::expect($page, fn (PendingAwaitablePage|AwaitableWebpage $page) => $page->fill(SEARCH_INPUT, 'abc'), 1);
    LivewireMonitor::expect($page, fn (PendingAwaitablePage|AwaitableWebpage $page) => $page->keys(SEARCH_INPUT, 'Escape'), 1);
    $page->assertScript(SEARCH_IS_OPEN, false);

    $page->click('Zoeken')->assertScript(SEARCH_IS_OPEN);
    LivewireMonitor::expect($page, fn (PendingAwaitablePage|AwaitableWebpage $page) => $page->keys(SEARCH_INPUT, 'Escape'), 0);
    $page->assertScript(SEARCH_IS_OPEN, false);
});
