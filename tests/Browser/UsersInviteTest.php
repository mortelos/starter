<?php

declare(strict_types=1);

use Mortelos\Ui\Testing\LivewireMonitor;
use Pest\Browser\Api\AwaitableWebpage;
use Pest\Browser\Api\PendingAwaitablePage;

it('invites, shows the link and undoes the invitation', function (): void {
    $page = verifyAsAdmin('/users');

    $invited = LivewireMonitor::expect($page, fn (PendingAwaitablePage|AwaitableWebpage $page) => $page
        ->fill('inviteEmail', 'collega@example.test')
        ->click('form[wire\:submit="invite"] button[type="submit"]'), 1);

    fwrite(STDERR, 'Screenshot na uitnodigen: '.$invited['screenshot'].PHP_EOL);

    $page->assertSee('Uitnodiging klaargezet voor collega@example.test.')
        ->assertScript("[...document.querySelectorAll('input[readonly]')].some((input) => input.value.includes('/invite/'))")
        ->assertSee('Openstaande uitnodigingen');

    LivewireMonitor::expect($page, fn (PendingAwaitablePage|AwaitableWebpage $page) => $page->click('Ongedaan maken'), 1);

    $page->assertSee('Uitnodiging voor collega@example.test ingetrokken.')
        ->assertDontSee('Uitnodigingslink');
    fwrite(STDERR, 'LivewireSummary '.json_encode(LivewireMonitor::summary($page), JSON_THROW_ON_ERROR).PHP_EOL);
});
