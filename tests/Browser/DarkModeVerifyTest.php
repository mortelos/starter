<?php

declare(strict_types=1);

use Mortelos\Ui\Testing\LivewireMonitor;
use Pest\Browser\Api\AwaitableWebpage;
use Pest\Browser\Api\PendingAwaitablePage;

// Whether the text of the first button with this label is clearly red. A hand-written text-red-600 on a
// ghost button loses to its neutral text colour, so this is where a danger action that is not red shows up.
const IS_RED_BUTTON = <<<'JS'
() => {
    const x = Object.assign(document.createElement('canvas'), { width: 1, height: 1 }).getContext('2d', { willReadFrequently: true });
    const button = [...document.querySelectorAll('button')].find(b => b.textContent.trim() === '%s');
    x.fillStyle = getComputedStyle(button).color; x.fillRect(0, 0, 1, 1);
    const [r, g, b] = x.getImageData(0, 0, 1, 1).data;
    return r - g > 60 && r - b > 60;
}
JS;

// The colour scheme is browser-wide configuration: reset it so later tests run in light mode.
afterEach(fn () => pest()->browser()->inLightMode());

it('keeps every text on the users screen readable in dark mode', function (string $system, ?string $stored): void {
    $system === 'dark' ? pest()->browser()->inDarkMode() : pest()->browser()->inLightMode();

    $page = verifyAsAdmin('/users');

    if ($stored !== null) {
        $page->script("() => localStorage.setItem('mortel.appearance', '{$stored}')");
        $page->navigate('/users');
    }

    expectNoPostsSinceLoad($page);

    $page->assertSee('Teamleden')
        ->assertScript("document.documentElement.classList.contains('dark')")
        ->assertScript('getComputedStyle(document.body).backgroundColor', 'rgb(24, 24, 27)');

    expect($page->script(LOW_CONTRAST_TEXTS))->toBe([]);
    fwrite(STDERR, 'LivewireSummary '.json_encode(LivewireMonitor::summary($page), JSON_THROW_ON_ERROR).PHP_EOL);
})->with([
    'system preference' => ['dark', null],
    'mortel.appearance against a light system' => ['light', 'dark'],
]);

it('keeps the users screen readable and shows revoking an invitation in red', function (string $scheme): void {
    $scheme === 'dark' ? pest()->browser()->inDarkMode() : pest()->browser()->inLightMode();

    $page = verifyAsAdmin('/users');

    LivewireMonitor::expect($page, fn (PendingAwaitablePage|AwaitableWebpage $page) => $page
        ->fill('inviteEmail', 'collega@example.test')
        ->click('form[wire\\:submit="invite"] button[type="submit"]'), 1);

    // Measured right after inviting, so the green success callout is on screen too (UTEQ-743).
    $page->assertPresent('[data-mortel-callout]')->assertSee('Openstaande uitnodigingen');

    expect($page->script(LOW_CONTRAST_TEXTS))->toBe([])
        ->and($page->script(sprintf(IS_RED_BUTTON, 'Intrekken')))->toBeTrue();
    fwrite(STDERR, 'LivewireSummary '.json_encode(LivewireMonitor::summary($page), JSON_THROW_ON_ERROR).PHP_EOL);
})->with(['light', 'dark']);
