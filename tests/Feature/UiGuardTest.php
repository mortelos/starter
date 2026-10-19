<?php

declare(strict_types=1);

use Mortelos\Ui\Testing\UiGuard;

it('builds all UI from x-mortel components', function () {
    UiGuard::assertClean(resource_path('views'));
});

it('keeps all app-owned code on the kit', function () {
    UiGuard::assertFluxFree(
        resource_path('views'),
        resource_path('css'),
        resource_path('js'),
        app_path(),
        base_path('tests'),
    );
});
