<?php

declare(strict_types=1);

use Mortelos\DevTools\Testing\OutletsGuard;

it('plugs into packages through their outlets and never changes them', function (): void {
    OutletsGuard::assertClean(base_path());
});
