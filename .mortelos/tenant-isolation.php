<?php

declare(strict_types=1);

return [
    'database/factories/UserFactory.php' => [
        'static-state' => [
            'count' => 1,
            'reason' => 'Permanent: Laravel standard test factory caches only the hash of a fixed test password; it contains no tenant or user state.',
            'issue' => 'https://github.com/mortelos/dev-tools/issues/20',
        ],
    ],
];
