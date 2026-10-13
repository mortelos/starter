<?php

declare(strict_types=1);

use Mortelos\DevTools\Testing\ArchGuard;

it('keeps writes in actions, events in aggregates and Livewire on the Livewire 4 paths', function (): void {
    ArchGuard::assertClean(base_path());
});
