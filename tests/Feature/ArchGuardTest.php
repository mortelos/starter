<?php

declare(strict_types=1);

use Mortelos\DevTools\Testing\ArchGuard;

it('keeps writes in actions, events in aggregates and Livewire on the Livewire 4 paths', function (): void {
    // Ratchet: de schrijfacties van het rollen- en instellingenscherm gaan in stap 2b naar
    // Actions (rollen via Mortel\Actions\Role en Policy). De getallen mogen alleen omlaag.
    ArchGuard::assertClean(base_path(), allow: [
        'resources/views/pages/governance/⚡roles.blade.php' => 7,
        'resources/views/pages/settings/⚡index.blade.php' => 2,
    ]);
});
