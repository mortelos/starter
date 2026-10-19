<?php

declare(strict_types=1);

use Mortelos\Ui\Testing\LivewireMonitor;
use Pest\Browser\Support\Screenshot;

it('keeps the main screens readable at each viewport and appearance', function (string $screen, int $width, string $appearance): void {
    $appearance === 'dark' ? pest()->browser()->inDarkMode() : pest()->browser()->inLightMode();

    $page = $screen === 'login' ? LivewireMonitor::visit('/login') : verifyAsAdmin('/dashboard');
    $page->resize($width, 900)->navigate('/'.$screen);

    expectNoPostsSinceLoad($page);
    $page->assertScript("document.documentElement.classList.contains('dark')", $appearance === 'dark');

    $name = 'kit-'.$screen.'-'.$width.'-'.$appearance;
    $page->screenshot(true, $name);

    fwrite(STDERR, 'KitScreen '.json_encode([
        'screen' => $screen,
        'width' => $width,
        'appearance' => $appearance,
        'summary' => LivewireMonitor::summary($page),
        'text' => $page->script('() => document.body.innerText'),
    ], JSON_THROW_ON_ERROR).PHP_EOL);

    $directory = getenv('KIT_SCREENSHOT_DIR');
    if (is_string($directory) && $directory !== '') {
        copy(Screenshot::path($name), $directory.'/'.$name.'.png');
    }
})->with(['dashboard', 'users', 'settings', 'inbox', 'governance', 'onboarding', 'login'])
    ->with([1440, 375])
    ->with(['light', 'dark']);
