<?php

declare(strict_types=1);

// Written by `php artisan mortelos:audit --write-baseline`. Counts may only go down.
return [
    'arch' => [],
    'heavy-in-loop' => [],
    'outlets' => [],
    'own-tables-log' => [],
    'settings' => [],
    'ui' => [
        'resources/views/auth/accept-invitation.blade.php' => [
            'theme-token' => 4,
        ],
        'resources/views/auth/two-factor-challenge.blade.php' => [
            'theme-token' => 6,
        ],
        'resources/views/components/auth/passkey-form.blade.php' => [
            'app-component' => 1,
        ],
        'resources/views/components/auth/password-form.blade.php' => [
            'app-component' => 1,
            'theme-token' => 3,
        ],
        'resources/views/components/inbox/⚡inbox-filter.blade.php' => [
            'theme-token' => 4,
        ],
        'resources/views/components/inbox/⚡inbox-list.blade.php' => [
            'theme-token' => 4,
        ],
        'resources/views/components/inbox/⚡intake-detail.blade.php' => [
            'theme-token' => 10,
        ],
        'resources/views/components/settings/⚡two-factor.blade.php' => [
            'theme-token' => 1,
        ],
        'resources/views/components/shared/⚡sidebar-nav.blade.php' => [
            'theme-token' => 8,
        ],
        'resources/views/components/shared/⚡topbar.blade.php' => [
            'theme-token' => 2,
        ],
        'resources/views/components/shared/⚡universal-search.blade.php' => [
            'theme-token' => 82,
        ],
        'resources/views/components/users/⚡user-access-slide-over.blade.php' => [
            'theme-token' => 20,
        ],
        'resources/views/layouts/app.blade.php' => [
            'theme-token' => 4,
        ],
        'resources/views/pages/auth/⚡login.blade.php' => [
            'theme-token' => 9,
        ],
        'resources/views/pages/governance/⚡index.blade.php' => [
            'theme-token' => 2,
        ],
        'resources/views/pages/governance/⚡roles.blade.php' => [
            'theme-token' => 24,
        ],
        'resources/views/pages/inbox/⚡index.blade.php' => [
            'theme-token' => 4,
        ],
        'resources/views/pages/onboarding/⚡index.blade.php' => [
            'theme-token' => 53,
        ],
        'resources/views/pages/users/⚡index.blade.php' => [
            'theme-token' => 5,
        ],
    ],
    'workflows' => [
        '.github/workflows/trigger-registry-rebuild.yml' => [
            'ci-shared-runner' => 1,
        ],
    ],
];
