<?php

declare(strict_types=1);

use Mortelos\DevTools\Testing\CodeLanguageGuard;

it('keeps code in English', function (): void {
    CodeLanguageGuard::assertClean(base_path());
});
