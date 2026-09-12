<?php

declare(strict_types=1);

use Mortelos\Ui\Testing\UiGuard;

it('builds all UI from x-mortel components', function () {
    UiGuard::assertClean(resource_path('views'));
});
