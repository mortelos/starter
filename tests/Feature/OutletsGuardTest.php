<?php

declare(strict_types=1);

use Mortelos\DevTools\Testing\OutletsGuard;
use Mortelos\DevTools\Testing\OutletsManifest;

it('plugs into packages through their outlets and never changes them', function (): void {
    OutletsGuard::assertClean(base_path());
});

it('lists the outlets of the starter itself in its manifest', function (): void {
    OutletsManifest::assertValid(base_path());
});
