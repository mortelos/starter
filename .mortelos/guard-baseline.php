<?php

declare(strict_types=1);

// Written by `php artisan mortelos:audit --write-baseline`. Counts may only go down.
return [
    'arch' => [],
    'outlets' => [],
    'own-tables-log' => [],
    'settings' => [],
    'ui' => [
        'resources/views/components/auth/passkey-form.blade.php' => [
            'app-component' => 1,
        ],
        'resources/views/components/auth/password-form.blade.php' => [
            'app-component' => 1,
        ],
    ],
    'workflows' => [
        '.github/workflows/trigger-registry-rebuild.yml' => [
            'ci-shared-runner' => 1,
        ],
    ],
];
