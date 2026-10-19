<?php

declare(strict_types=1);

it('imports the kit styles after the mortel theme layer', function () {
    $css = (string) file_get_contents(resource_path('css/app.css'));
    $kit = strpos($css, "@import '../../vendor/mortelos/ui/resources/css/kit.css';");
    $mortel = strpos($css, "@import '../../vendor/mortelos/ui/resources/css/mortel.css';");

    expect($kit)->not->toBeFalse()
        ->and($mortel)->not->toBeFalse()
        ->and($kit)->toBeGreaterThan((int) $mortel);
});

it('requires the own kit release and starts new apps in Dutch', function () {
    /** @var array{require: array<string, string>} $composer */
    $composer = json_decode((string) file_get_contents(base_path('composer.json')), true);
    $environment = (string) file_get_contents(base_path('.env.example'));

    expect($composer['require']['mortelos/ui'])->toBe('^0.5.15')
        ->and($environment)->toContain('MORTEL_UI_KIT=own', 'APP_LOCALE=nl', 'APP_FALLBACK_LOCALE=en');
});
