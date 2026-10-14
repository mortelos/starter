<?php

declare(strict_types=1);

use App\Models\User;
use Mortel\Models\InboxItem;
use Mortelos\Ui\Testing\LivewireMonitor;
use Pest\Browser\Api\AwaitableWebpage;
use Pest\Browser\Api\PendingAwaitablePage;

const INBOX_LIST = 'nav[aria-label="Inbox navigatie"]';

const INBOX_FILTERS = '[aria-controls="inbox-filter-groups"]';

function inboxItemForAdmin(string $title, string $type = 'voorstel'): InboxItem
{
    // @spine-ignore: test fixture, the starter itself never creates inbox items
    return InboxItem::factory()->forUser(User::query()->where('email', 'admin@example.test')->firstOrFail())->create([
        'title' => $title,
        'summary' => 'Samenvatting van '.$title.'.',
        'type' => $type,
        'risk_level' => 'low',
    ]);
}

it('shows the empty inbox within the verify budgets', function (): void {
    $page = verifyAsAdmin('/inbox');

    expectNoPostsSinceLoad($page);

    $page->assertSee('Je inbox is leeg')
        ->assertSee('Kies een item');
});

it('filters, opens an item and approves it on a wide screen', function (): void {
    inboxItemForAdmin('Factuur controleren');
    inboxItemForAdmin('Nieuwe klant gemeld', 'notificatie');

    $page = verifyAsAdmin('/inbox');
    expectNoPostsSinceLoad($page);

    LivewireMonitor::expect($page, fn (PendingAwaitablePage|AwaitableWebpage $page) => $page->click(INBOX_FILTERS), 0);
    LivewireMonitor::expect($page, fn (PendingAwaitablePage|AwaitableWebpage $page) => $page->click('#inbox-filter-groups >> text=Notificatie'), 2);
    $page->assertSeeIn(INBOX_LIST, 'Nieuwe klant gemeld')
        ->assertDontSeeIn(INBOX_LIST, 'Factuur controleren')
        ->assertQueryStringHas('filterType', 'notificatie');

    LivewireMonitor::expect($page, fn (PendingAwaitablePage|AwaitableWebpage $page) => $page->click('Wis filters'), 1);
    $page->assertSeeIn(INBOX_LIST, 'Factuur controleren')
        ->assertQueryStringMissing('filterType');

    LivewireMonitor::expect($page, fn (PendingAwaitablePage|AwaitableWebpage $page) => $page->click(INBOX_LIST.' >> text=Factuur controleren'), 1);
    $page->assertSee('Samenvatting van Factuur controleren.')
        ->assertVisible(INBOX_LIST);

    LivewireMonitor::expect($page, fn (PendingAwaitablePage|AwaitableWebpage $page) => $page->click('Goedkeuren'), 2);
    $page->assertDontSeeIn(INBOX_LIST, 'Factuur controleren')
        ->assertSee('Kies een item');
});

it('hides the list behind the detail on a phone and a tablet and brings it back', function (int $width): void {
    inboxItemForAdmin('Factuur controleren');

    // Load the page at this width, as a phone or tablet does; a resize afterwards is a layout shift of its own.
    $page = verifyAsAdmin('/dashboard');
    $page->resize($width, 900);
    $page->navigate('/inbox');
    expectNoPostsSinceLoad($page);
    $page->assertVisible(INBOX_LIST);

    LivewireMonitor::expect($page, fn (PendingAwaitablePage|AwaitableWebpage $page) => $page->click(INBOX_LIST.' >> text=Factuur controleren'), 1);
    $page->assertMissing(INBOX_LIST)
        ->assertSee('Samenvatting van Factuur controleren.');

    LivewireMonitor::expect($page, fn (PendingAwaitablePage|AwaitableWebpage $page) => $page->click('Terug naar lijst'), 1);
    $page->assertVisible(INBOX_LIST)
        ->assertSeeIn(INBOX_LIST, 'Factuur controleren');
})->with([
    'phone' => 375,
    'tablet' => 800,
]);
