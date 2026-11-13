<?php

declare(strict_types=1);

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
use Symfony\Component\Finder\SplFileInfo;
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

    return collect(File::allFiles(app_path('Jobs')))
        ->map(fn (SplFileInfo $file): string => 'App\\Jobs\\'.str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname()))
        ->filter(fn (string $class): bool => class_exists($class) && is_subclass_of($class, ShouldQueue::class))
        ->sort()
        ->values()
        ->all();
}

/** The jobs of mortelos/framework that name a queue of their own, as the framework dispatches them. */
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

it('keeps the timeout of the framework jobs under the supervisor, and that under retry_after', function (ShouldQueue $job): void {
    assertJobFits($job);
})->with(fn (): array => array_map(fn (ShouldQueue $job): array => [$job], frameworkJobs()));

it('reads every queue the framework sends jobs to', function (): void {
    $named = [];

    foreach (File::allFiles(base_path('vendor/mortelos/framework/src/Jobs')) as $file) {
        preg_match_all("/onQueue\\('([a-z-]+)'\\)/", $file->getContents(), $matches);
        $named = [...$named, ...$matches[1]];
    }

    $read = [];

    foreach ((array) config('horizon.environments.production') as $supervisor) {
        $read = [...$read, ...(array) ($supervisor['queue'] ?? [])];
    }

    expect(array_unique($named))->not->toBe([])->and(array_diff($named, $read))->toBe([]);
});

it('fails for a job whose timeout is not below the timeout of its supervisor', function (): void {
    $job = new class implements ShouldQueue
    {
        use Dispatchable, Queueable;

        public int $timeout = 250; // the supervisor `default` stops a job after 200 s

        public function handle(): void {}
    };

    // One finding per environment of config/horizon.php.
    expect(QueueTimeouts::violations($job))->toHaveCount(3)->and(QueueTimeouts::violations($job)[0])->toContain('times out after 250 s, not below the 200 s of supervisor default (production)');
    expect(fn () => assertJobFits($job))->toThrow(ExpectationFailedException::class);
});

it('fails for a job whose timeout equals the timeout of its supervisor', function (): void {
    $job = new class implements ShouldQueue
    {
        use Dispatchable, Queueable;

        public int $timeout = 200;

        public function handle(): void {}
    };

    expect(fn () => assertJobFits($job))->toThrow(ExpectationFailedException::class);
});

it('fails when the supervisor does not time out before retry_after of its connection', function (): void {
    config(['queue.connections.redis.retry_after' => 200]);

    $job = new class implements ShouldQueue
    {
        use Dispatchable, Queueable;

        public function handle(): void {}
    };

    expect(QueueTimeouts::violations($job)[0] ?? '')->toContain('supervisor default times out after 200 s, not below retry_after 200 s');
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
        $supervisors = (array) config("horizon.environments.{$environment}");

        expect(array_keys($supervisors))->toBe(['default'])
            ->and($supervisors['default']['connection'])->toBe('redis')
            ->and($supervisors['default']['queue'])->toBe(['agents', 'webhooks', 'default']);
    }
});

it('warns about a long wait on every queue a supervisor reads', function (): void {
    $waits = array_keys((array) config('horizon.waits'));

    foreach ((array) config('horizon.environments.production') as $supervisor) {
        foreach ((array) $supervisor['queue'] as $queue) {
            expect($waits)->toContain($supervisor['connection'].':'.$queue);
        }
    }
});

it('keeps failed jobs in the database, with Horizon holding a copy', function (): void {
    expect(config('queue.failed.driver'))->toBe('database-uuids')
        ->and(Schema::hasTable((string) config('queue.failed.table')))->toBeTrue()
        ->and(config('horizon.trim.failed'))->toBeGreaterThan(0);
});

it('runs horizon:snapshot from the scheduler', function (): void {
    $commands = collect(app(Schedule::class)->events())->map(fn ($event): string => (string) $event->command);

    expect($commands->contains(fn (string $command): bool => str_contains($command, 'horizon:snapshot')))->toBeTrue();
});

it('keeps the database queue as the default of the starter', function (): void {
    expect((string) file_get_contents(base_path('.env.example')))->toMatch('/^QUEUE_CONNECTION=database$/m');
});

/** Loads config/horizon.php again under other environment values, as Laravel does at boot. */
function horizonConfigWith(array $env): array
{
    $original = [$_ENV, $_SERVER];

    try {
        foreach (['HORIZON_PREFIX', 'APP_ENV', 'APP_NAME'] as $name) {
            unset($_ENV[$name], $_SERVER[$name]);
        }

        foreach ($env as $name => $value) {
            $_ENV[$name] = $_SERVER[$name] = $value;
        }

        /** @var array<string, mixed> $config */
        $config = require config_path('horizon.php');

        return $config;
    } finally {
        [$_ENV, $_SERVER] = $original;
    }
}

it('gives a local machine a prefix per worktree when HORIZON_PREFIX is empty', function (): void {
    $prefix = horizonConfigWith(['APP_ENV' => 'local'])['prefix'];

    expect($prefix)->toBe('horizon-'.substr(hash('sha256', base_path()), 0, 12).':');
});

it('uses a prefix that follows the app name outside a local machine, so a deploy keeps its metrics', function (): void {
    expect(horizonConfigWith(['APP_ENV' => 'production', 'APP_NAME' => 'Mijn Portaal'])['prefix'])->toBe('mijn-portaal-horizon:');
});

it('lets HORIZON_PREFIX win', function (): void {
    expect(horizonConfigWith(['APP_ENV' => 'local', 'HORIZON_PREFIX' => 'custom:'])['prefix'])->toBe('custom:');
});
