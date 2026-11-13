<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Mortel\Jobs\ContinueAgentRunJob;
use Mortel\Jobs\DeliverWebhookJob;
use Mortel\Jobs\ExecuteAgentRunJob;
use PHPUnit\Framework\ExpectationFailedException;
use Tests\Support\QueueTimeouts;

uses(RefreshDatabase::class);

/*
 * Every queued job fits the supervisor that runs it, and that supervisor fits the connection (Tests\Support\QueueTimeouts):
 *
 *   job timeout  <  timeout of its supervisor  <  retry_after of its connection
 *
 * A new job in app/Jobs fails the coverage test until it is listed in queuedJobs(), so it cannot skip the check.
 */

/**
 * One instance of every job in app/Jobs, with dummy constructor arguments. The class is the key.
 *
 * @return array<class-string<ShouldQueue>, ShouldQueue>
 */
function queuedJobs(): array
{
    return [
        // App\Jobs\SendReminderJob::class => new SendReminderJob(1),
    ];
}

/** @return list<class-string<ShouldQueue>> */
function appJobClasses(): array
{
    if (! File::isDirectory(app_path('Jobs'))) {
        return [];
    }

    $classes = [];

    foreach (File::allFiles(app_path('Jobs')) as $file) {
        $class = 'App\\Jobs\\'.str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname());

        if (class_exists($class) && is_subclass_of($class, ShouldQueue::class)) {
            $classes[] = $class;
        }
    }

    sort($classes);

    return $classes;
}

/**
 * The supervisors of one environment of config/horizon.php, checked while they are read.
 *
 * @return array<string, array{connection: string, queue: list<string>}>
 */
function horizonSupervisors(string $environment): array
{
    $supervisors = [];

    foreach (config()->array("horizon.environments.{$environment}") as $name => $supervisor) {
        $connection = is_array($supervisor) ? ($supervisor['connection'] ?? null) : null;
        $queues = is_array($supervisor) && is_array($supervisor['queue'] ?? null) ? $supervisor['queue'] : [];

        if (! is_string($connection)) {
            throw new RuntimeException("Supervisor {$name} of {$environment} has no connection.");
        }

        $supervisors[(string) $name] = ['connection' => $connection, 'queue' => array_values(array_filter($queues, 'is_string'))];
    }

    return $supervisors;
}

/**
 * The jobs of mortelos/framework that name a queue of their own, as the framework dispatches them. Built inside the
 * test: the constructors (0.7.84 too) read the config, which does not exist while Pest collects the data sets.
 *
 * @return array<class-string<ShouldQueue>, ShouldQueue>
 */
function frameworkJobs(): array
{
    return [
        ExecuteAgentRunJob::class => new ExecuteAgentRunJob('guard'),
        ContinueAgentRunJob::class => new ContinueAgentRunJob('guard', 'guard'),
        DeliverWebhookJob::class => (new ReflectionClass(DeliverWebhookJob::class))->newInstanceWithoutConstructor()->onQueue('webhooks'),
    ];
}

/** The check every real job goes through; the proof below shows that it fails for a job that does not fit. */
function assertJobFits(ShouldQueue $job): void
{
    expect(QueueTimeouts::violations($job))->toBe([]);
}

it('covers every job in app/Jobs', function (): void {
    expect(array_keys(queuedJobs()))->toEqualCanonicalizing(appJobClasses());
});

it('keeps the timeout of every job of the app under its supervisor, and that under retry_after', function (): void {
    // No jobs yet in the starter: an empty data set would be an error, a loop is not.
    foreach (queuedJobs() as $job) {
        assertJobFits($job);
    }

    expect(true)->toBeTrue();
});

it('keeps the timeout of the framework jobs under the supervisor, and that under retry_after', function (string $class): void {
    assertJobFits(frameworkJobs()[$class]);
})->with([ExecuteAgentRunJob::class, ContinueAgentRunJob::class, DeliverWebhookJob::class]);

it('reads every queue the framework sends jobs to', function (): void {
    $named = [];

    foreach (File::allFiles(base_path('vendor/mortelos/framework/src/Jobs')) as $file) {
        preg_match_all("/onQueue\\('([a-z-]+)'\\)/", $file->getContents(), $matches);
        $named = [...$named, ...$matches[1]];
    }

    $read = [];

    foreach (horizonSupervisors('production') as $supervisor) {
        $read = [...$read, ...$supervisor['queue']];
    }

    expect(array_unique($named))->not->toBe([])->and(array_diff($named, $read))->toBe([]);
});

it('fails for a job whose timeout is not below the timeout of its supervisor', function (): void {
    $job = new class implements ShouldQueue
    {
        use Dispatchable, Queueable;

        public int $timeout = 400; // the supervisor `default` stops a job after 360 s

        public function handle(): void {}
    };

    // One finding per environment of config/horizon.php.
    expect(QueueTimeouts::violations($job))->toHaveCount(3)->and(QueueTimeouts::violations($job)[0])->toContain('times out after 400 s, not below the 360 s of supervisor default (production)');
    expect(fn () => assertJobFits($job))->toThrow(ExpectationFailedException::class);
});

it('fails for a job whose timeout equals the timeout of its supervisor', function (): void {
    $job = new class implements ShouldQueue
    {
        use Dispatchable, Queueable;

        public int $timeout = 360;

        public function handle(): void {}
    };

    expect(fn () => assertJobFits($job))->toThrow(ExpectationFailedException::class);
});

it('fails when the supervisor does not time out before retry_after of its connection', function (): void {
    config(['queue.connections.redis.retry_after' => 360]);

    $job = new class implements ShouldQueue
    {
        use Dispatchable, Queueable;

        public function handle(): void {}
    };

    expect(QueueTimeouts::violations($job)[0] ?? '')->toContain('supervisor default times out after 360 s, not below retry_after 360 s');
    expect(fn () => assertJobFits($job))->toThrow(ExpectationFailedException::class);
});

it('fails for a job on a queue that no supervisor reads', function (): void {
    $job = new class implements ShouldQueue
    {
        use Dispatchable, Queueable;

        public function __construct()
        {
            $this->onQueue('nobody-reads-this');
        }

        public function handle(): void {}
    };

    expect(QueueTimeouts::violations($job)[0] ?? '')->toContain('is read by no supervisor');
    expect(fn () => assertJobFits($job))->toThrow(ExpectationFailedException::class);
});

it('passes a job that fits', function (): void {
    $job = new class implements ShouldQueue
    {
        use Dispatchable, Queueable;

        public int $timeout = 120;

        public function handle(): void {}
    };

    assertJobFits($job);
});

it('ships one supervisor, default, on redis', function (): void {
    foreach (['production', 'staging', 'local'] as $environment) {
        $supervisors = horizonSupervisors($environment);

        expect(array_keys($supervisors))->toBe(['default'])
            ->and($supervisors['default']['connection'])->toBe('redis')
            ->and($supervisors['default']['queue'])->toBe(['agents', 'webhooks', 'default']);
    }
});

it('warns about a long wait on every queue a supervisor reads', function (): void {
    $waits = array_keys(config()->array('horizon.waits'));

    foreach (horizonSupervisors('production') as $supervisor) {
        foreach ($supervisor['queue'] as $queue) {
            expect($waits)->toContain($supervisor['connection'].':'.$queue);
        }
    }
});

it('keeps failed jobs in the database, with Horizon holding a copy', function (): void {
    expect(config('queue.failed.driver'))->toBe('database-uuids')
        ->and(Schema::hasTable(config()->string('queue.failed.table')))->toBeTrue()
        ->and(config('horizon.trim.failed'))->toBeGreaterThan(0);
});

it('runs horizon:snapshot from the scheduler', function (): void {
    $commands = collect(app(Schedule::class)->events())->map(fn ($event): string => (string) $event->command);

    expect($commands->contains(fn (string $command): bool => str_contains($command, 'horizon:snapshot')))->toBeTrue();
});

it('keeps the database queue as the default of the starter', function (): void {
    expect(File::get(base_path('.env.example')))->toMatch('/^QUEUE_CONNECTION=database$/m');
});

/**
 * Loads a config file again under other environment values, as Laravel does at boot.
 *
 * @param  array<string, string>  $env
 * @return array<array-key, mixed>
 */
function configFileWith(string $file, array $env): array
{
    $original = [$_ENV, $_SERVER];

    try {
        foreach (['HORIZON_PREFIX', 'REDIS_PREFIX', 'APP_ENV', 'APP_NAME'] as $name) {
            unset($_ENV[$name], $_SERVER[$name]);
        }

        foreach ($env as $name => $value) {
            $_ENV[$name] = $_SERVER[$name] = $value;
        }

        $config = require config_path($file);

        if (! is_array($config)) {
            throw new RuntimeException("config/{$file} does not return an array.");
        }

        return $config;
    } finally {
        [$_ENV, $_SERVER] = $original;
    }
}

/**
 * @param  array<string, string>  $env
 * @return array<array-key, mixed>
 */
function horizonConfigWith(array $env): array
{
    return configFileWith('horizon.php', $env);
}

/**
 * The prefix Laravel puts before every Redis key, so before the queue keys too.
 *
 * @param  array<string, string>  $env
 */
function redisKeyPrefixWith(array $env): string
{
    $options = configFileWith('database.php', $env)['redis'] ?? [];
    $prefix = is_array($options) && is_array($options['options'] ?? null) ? ($options['options']['prefix'] ?? null) : null;

    if (! is_string($prefix)) {
        throw new RuntimeException('config/database.php has no redis prefix.');
    }

    return $prefix;
}

it('gives a local machine a prefix per worktree when HORIZON_PREFIX is empty', function (): void {
    $prefix = horizonConfigWith(['APP_ENV' => 'local'])['prefix'];

    expect($prefix)->toBe('horizon-'.substr(hash('sha256', base_path()), 0, 12).':');
});

it('uses a prefix that follows the app name outside a local machine, so a deploy keeps its metrics', function (): void {
    expect(horizonConfigWith(['APP_ENV' => 'production', 'APP_NAME' => 'My Portal'])['prefix'])->toBe('my-portal-horizon:');
});

it('lets HORIZON_PREFIX win', function (): void {
    expect(horizonConfigWith(['APP_ENV' => 'local', 'HORIZON_PREFIX' => 'custom:'])['prefix'])->toBe('custom:');
});

it('also gives the queue keys in Redis a prefix per worktree on a local machine, with the same hash as Horizon', function (): void {
    $hash = substr(hash('sha256', base_path()), 0, 12);

    $keys = redisKeyPrefixWith(['APP_ENV' => 'local', 'APP_NAME' => 'My Portal']);

    expect($keys)->toBe('my-portal-database-'.$hash.'-')
        ->and(horizonConfigWith(['APP_ENV' => 'local'])['prefix'])->toContain($hash);
});

it('treats an empty REDIS_PREFIX as no prefix at all, so a local worktree still gets its own', function (): void {
    $hash = substr(hash('sha256', base_path()), 0, 12);

    expect(redisKeyPrefixWith(['APP_ENV' => 'local', 'APP_NAME' => 'My Portal', 'REDIS_PREFIX' => '']))->toContain($hash);
});

it('keeps the Redis key prefix stable outside a local machine, so a deploy keeps its queue', function (): void {
    expect(redisKeyPrefixWith(['APP_ENV' => 'production', 'APP_NAME' => 'My Portal']))->toBe('my-portal-database-')
        ->and(redisKeyPrefixWith(['APP_ENV' => 'staging', 'APP_NAME' => 'My Portal']))->toBe('my-portal-database-');
});

it('lets REDIS_PREFIX win, locally too', function (): void {
    expect(redisKeyPrefixWith(['APP_ENV' => 'local', 'REDIS_PREFIX' => 'own-prefix-']))->toBe('own-prefix-')
        ->and(redisKeyPrefixWith(['APP_ENV' => 'production', 'REDIS_PREFIX' => 'own-prefix-']))->toBe('own-prefix-');
});

it('plans horizon:snapshot only while the queue is on Redis', function (): void {
    $snapshot = collect(app(Schedule::class)->events())
        ->first(fn ($event): bool => str_contains((string) $event->command, 'horizon:snapshot'));

    if (! $snapshot instanceof Event) {
        throw new RuntimeException('horizon:snapshot is not in the schedule.');
    }

    foreach (['sync', 'database'] as $connection) {
        config(['queue.default' => $connection]);
        expect($snapshot->filtersPass(app()))->toBeFalse("horizon:snapshot is planned on the {$connection} queue");
    }

    config(['queue.default' => 'redis']);
    expect($snapshot->filtersPass(app()))->toBeTrue();
});
