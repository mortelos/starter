<?php

declare(strict_types=1);

use Illuminate\Support\Str;

/*
 * Laravel Horizon on Redis (docs/horizon.md). Without QUEUE_CONNECTION=redis the starter keeps working on the
 * database queue and nothing here runs.
 *
 * config/horizon.php loads before config/queue.php (alphabetical order), so config('queue...') is not available
 * here: the values the two files share are written out and a test (tests/Feature/HorizonConfigTest.php) compares them.
 */

$isLocal = env('APP_ENV') === 'local';

// Per worktree on a development machine: the same Redis is shared by every worktree, and without its own prefix
// they would show each other's failures. The queue keys have their own prefix, REDIS_PREFIX in config/database.php,
// which gets the same hash there; without that the worktrees would still run each other's jobs. Anywhere else the prefix follows the app name,
// not the path (a release directory changes with every deploy and would lose the metrics and failed jobs).
$prefix = filled(env('HORIZON_PREFIX'))
    ? (string) env('HORIZON_PREFIX')
    : ($isLocal ? 'horizon-'.substr(hash('sha256', base_path()), 0, 12).':' : Str::slug((string) env('APP_NAME', 'laravel')).'-horizon:');

/*
 * The supervisor the starter ships. An app adds its own next to it (or replaces it) and keeps three rules, which
 * HorizonConfigTest checks for every job: the timeout of a job is below the timeout of the supervisor that runs it,
 * and that is below `retry_after` of its connection (config/queue.php), or a second worker takes a job that is
 * still running.
 *
 *   agents    the agent runs of mortelos/framework (ExecuteAgentRunJob, ContinueAgentRunJob): 150 s.
 *   webhooks  DeliverWebhookJob: 30 s.
 *   default   everything without a queue of its own.
 */
$supervisors = [
    'default' => [
        'connection' => 'redis',
        'queue' => ['agents', 'webhooks', 'default'],
        'balance' => 'simple',
        'maxProcesses' => 3,
        'tries' => 1,
        'timeout' => 200,
        'memory' => 256,
        'nice' => 0,
    ],
];

return [

    'name' => env('HORIZON_NAME'),

    'domain' => env('HORIZON_DOMAIN'),

    'path' => env('HORIZON_PATH', 'horizon'),

    // The Redis connection of config/database.php that holds the queue and Horizon's own data.
    'use' => env('HORIZON_REDIS_CONNECTION', env('REDIS_QUEUE_CONNECTION', 'default')),

    'prefix' => $prefix,

    // Access is the gate `viewHorizon` (HorizonServiceProvider): the ability `horizon.view` in the governance policy.
    'middleware' => ['web'],

    'notification_mail' => env('HORIZON_NOTIFICATION_MAIL'),

    // A queue that waits longer than this many seconds raises LongWaitDetected, which HorizonServiceProvider logs.
    'waits' => [
        'redis:agents' => 120,
        'redis:webhooks' => 120,
        'redis:default' => 60,
    ],

    // Minutes. Failed jobs stay in the database (`failed_jobs`, driver database-uuids, config/queue.php): Horizon
    // keeps only a copy to show.
    'trim' => [
        'recent' => 60,
        'pending' => 60,
        'completed' => 60,
        'recent_failed' => 1440,
        'failed' => 4320,
        'monitored' => 10080,
    ],

    'silenced' => [],

    'silenced_tags' => [],

    'metrics' => [
        'trim_snapshots' => [
            'job' => 24,
            'queue' => 24,
        ],
    ],

    'fast_termination' => false,

    'memory_limit' => (int) env('HORIZON_MEMORY_LIMIT', 128),

    'defaults' => [],

    'environments' => [
        'production' => $supervisors,
        'staging' => $supervisors,
        'local' => $supervisors,
    ],

    'watch' => [
        'app',
        'bootstrap',
        'config/**/*.php',
        'database/**/*.php',
        'public/**/*.php',
        'resources/**/*.php',
        'routes',
        'composer.lock',
        'composer.json',
        '.env',
    ],

];
