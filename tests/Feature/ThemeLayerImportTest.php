<?php

declare(strict_types=1);

it('imports the mortel theme layer right after Flux', function () {
    $css = (string) file_get_contents(resource_path('css/app.css'));
    $flux = strpos($css, "@import '../../vendor/livewire/flux/dist/flux.css';");
    $mortel = strpos($css, "@import '../../vendor/mortelos/ui/resources/css/mortel.css';");

    expect($flux)->not->toBeFalse()
        ->and($mortel)->not->toBeFalse()
        ->and($mortel)->toBeGreaterThan((int) $flux);
});

it('requires a mortelos/ui release that ships the theme layer', function () {
    /** @var array{require: array<string, string>} $composer */
    $composer = json_decode((string) file_get_contents(base_path('composer.json')), true);

    expect($composer['require']['mortelos/ui'])->toBe('^0.4.6')
        ->and(file_exists(base_path('vendor/mortelos/ui/resources/css/mortel.css')))->toBeTrue();
});
