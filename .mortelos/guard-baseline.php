<?php

declare(strict_types=1);

// Written by `php artisan mortelos:audit --write-baseline`. Counts may only go down.
return [
    'ui' => [
        'resources/views/components/auth/passkey-form.blade.php' => [
            'app-component' => 1,
        ],
        'resources/views/components/auth/password-form.blade.php' => [
            'app-component' => 1,
        ],
        'resources/views/components/inbox/⚡inbox-detail.blade.php' => [
            'app-component' => 6,
        ],
        'resources/views/components/inbox/⚡inbox-filter.blade.php' => [
            'app-component' => 4,
        ],
        'resources/views/components/inbox/⚡inbox-list.blade.php' => [
            'app-component' => 3,
        ],
        'resources/views/components/inbox/⚡intake-detail.blade.php' => [
            'app-component' => 4,
        ],
        'resources/views/components/settings/⚡two-factor.blade.php' => [
            'app-component' => 10,
        ],
        'resources/views/components/shared/⚡universal-search.blade.php' => [
            'app-component' => 11,
        ],
        'resources/views/components/users/⚡user-access-slide-over.blade.php' => [
            'app-component' => 4,
        ],
    ],
    'workflows' => [
        '.github/workflows/trigger-registry-rebuild.yml' => [
            'ci-shared-runner' => 1,
        ],
    ],
];
