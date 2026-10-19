<?php

declare(strict_types=1);

use Mortelos\Ui\Testing\LivewireMonitor;
use Pest\Browser\Api\AwaitableWebpage;
use Pest\Browser\Api\PendingAwaitablePage;

// Every visible text on the page against its real background (translucent layers stacked up to the
// first opaque ancestor), WCAG contrast below 4.5. Colours go through a canvas pixel, so oklch() counts.
const LOW_CONTRAST_TEXTS = <<<'JS'
() => {
    const x = Object.assign(document.createElement('canvas'), { width: 1, height: 1 }).getContext('2d', { willReadFrequently: true });
    const px = (c, under) => { x.clearRect(0, 0, 1, 1); if (under) { x.fillStyle = `rgb(${under})`; x.fillRect(0, 0, 1, 1); } x.fillStyle = '#0000'; x.fillStyle = c; x.fillRect(0, 0, 1, 1); return [...x.getImageData(0, 0, 1, 1).data]; };
    const lum = (rgb) => rgb.slice(0, 3).map(v => (v /= 255) <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4).reduce((s, v, i) => s + v * [0.2126, 0.7152, 0.0722][i], 0);
    const background = (el) => {
        const layers = [];
        for (let e = el; e; e = e.parentElement) { const c = getComputedStyle(e).backgroundColor, a = px(c)[3]; if (a > 0) { layers.push(c); if (a === 255) break; } }
        return layers.reverse().reduce((under, c) => px(c, under.slice(0, 3)), [255, 255, 255]);
    };
    return [...document.body.querySelectorAll('*')].filter(e => {
        const r = e.getBoundingClientRect();
        return r.width > 1 && r.height > 1 && getComputedStyle(e).visibility !== 'hidden' && !e.closest('template')
            && [...e.childNodes].some(n => n.nodeType === 3 && n.textContent.trim().length > 1);
    }).map(e => {
        const bg = background(e), [a, b] = [lum(px(getComputedStyle(e).color, bg.slice(0, 3))), lum(bg)];
        return [e.textContent.trim().slice(0, 40), +((Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05)).toFixed(2)];
    }).filter(([, ratio]) => ratio < 4.5);
}
JS;

// Whether the text of the first button with this label is clearly red. A hand-written text-red-600 on a
// ghost button loses to Flux' own text-zinc-800, so this is where a danger action that is not red shows up.
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
        $page->script("() => localStorage.setItem('flux.appearance', '{$stored}')");
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
    'flux.appearance against a light system' => ['light', 'dark'],
]);

it('keeps the users screen readable and shows revoking an invitation in red', function (string $scheme): void {
    $scheme === 'dark' ? pest()->browser()->inDarkMode() : pest()->browser()->inLightMode();

    $page = verifyAsAdmin('/users');

    LivewireMonitor::expect($page, fn (PendingAwaitablePage|AwaitableWebpage $page) => $page
        ->fill('inviteEmail', 'collega@example.test')
        ->click('form[wire\\:submit="invite"] button[type="submit"]'), 1);

    // Measured right after inviting, so the green success callout is on screen too (UTEQ-743).
    $page->assertPresent('[data-flux-callout]')->assertSee('Openstaande uitnodigingen');

    expect($page->script(LOW_CONTRAST_TEXTS))->toBe([])
        ->and($page->script(sprintf(IS_RED_BUTTON, 'Intrekken')))->toBeTrue();
    fwrite(STDERR, 'LivewireSummary '.json_encode(LivewireMonitor::summary($page), JSON_THROW_ON_ERROR).PHP_EOL);
})->with(['light', 'dark']);
