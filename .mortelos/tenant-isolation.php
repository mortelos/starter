<?php

declare(strict_types=1);

return [
    'app/Http/Middleware/SharePolicyCacheForRequest.php' => [
        'singleton' => [
            'count' => 2,
            'reason' => 'Permanent: the one-request PolicyCache is put in the container for the request and removed (or the outer one put back) in finally, so queue jobs, agent runs and commands get a fresh cache; the starter has no tenancy of its own, and an app that adds tenant switching must also empty the cache at every tenancy change.',
            'issue' => 'https://github.com/mortelos/dev-tools/issues/20',
        ],
    ],
    'database/factories/UserFactory.php' => [
        'static-state' => [
            'count' => 1,
            'reason' => 'Permanent: Laravel standard test factory caches only the hash of a fixed test password; it contains no tenant or user state.',
            'issue' => 'https://github.com/mortelos/dev-tools/issues/20',
        ],
    ],
];
