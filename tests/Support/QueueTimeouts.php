<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Contracts\Queue\ShouldQueue;
use ReflectionObject;

/**
 * The timeout rules of a Redis queue, for one job at a time (tests/Feature/HorizonConfigTest.php):
 *
 *   job timeout  <  timeout of the supervisor that runs the job  <  retry_after of its connection
 *
 * A job that outlives its supervisor's timeout is killed by the worker; a supervisor that outlives `retry_after` has
 * its running job handed to a second worker. A job without its own timeout runs under the supervisor's, so only the
 * second rule applies to it. Every environment of config/horizon.php is checked.
 */
final class QueueTimeouts
{
    /**
     * @return list<string> what is wrong, empty when the job fits
     */
    public static function violations(ShouldQueue $job): array
    {
        $violations = [];
        $class = $job::class;
        $connection = self::connection($job);
        $queue = self::queue($job, $connection);
        $retryAfter = config("queue.connections.{$connection}.retry_after");

        foreach (array_keys(self::environments()) as $environment) {
            $supervisors = self::supervisorsReading($environment, $connection, $queue);

            if ($supervisors === []) {
                $violations[] = "{$class} on {$connection}:{$queue} is read by no supervisor ({$environment})";

                continue;
            }

            foreach ($supervisors as $name => $supervisor) {
                $supervisorTimeout = (int) ($supervisor['timeout'] ?? 60);
                $jobTimeout = self::jobTimeout($job);

                if ($jobTimeout !== null && $jobTimeout >= $supervisorTimeout) {
                    $violations[] = "{$class} times out after {$jobTimeout} s, not below the {$supervisorTimeout} s of supervisor {$name} ({$environment})";
                }

                if (! is_int($retryAfter)) {
                    $violations[] = "queue.connections.{$connection}.retry_after is not set ({$environment})";
                } elseif ($supervisorTimeout >= $retryAfter) {
                    $violations[] = "supervisor {$name} times out after {$supervisorTimeout} s, not below retry_after {$retryAfter} s of {$connection} ({$environment})";
                }
            }
        }

        return array_values(array_unique($violations));
    }

    /** The job's own timeout, null when it sets none. */
    public static function jobTimeout(ShouldQueue $job): ?int
    {
        $timeout = self::property($job, 'timeout');

        return is_numeric($timeout) ? (int) $timeout : null;
    }

    /** A public property that the Queueable trait or the job declares; null when there is none or it is unset. */
    private static function property(ShouldQueue $job, string $name): mixed
    {
        $reflection = new ReflectionObject($job);

        if (! $reflection->hasProperty($name)) {
            return null;
        }

        $property = $reflection->getProperty($name);

        return $property->isInitialized($job) ? $property->getValue($job) : null;
    }

    /** Horizon runs on Redis: a job without a connection of its own is judged on `redis`, whatever QUEUE_CONNECTION is. */
    private static function connection(ShouldQueue $job): string
    {
        $connection = self::property($job, 'connection');

        return is_string($connection) && $connection !== '' ? $connection : 'redis';
    }

    private static function queue(ShouldQueue $job, string $connection): string
    {
        $queue = self::property($job, 'queue');

        if (is_string($queue) && $queue !== '') {
            return $queue;
        }

        $default = config("queue.connections.{$connection}.queue");

        return is_string($default) && $default !== '' ? $default : 'default';
    }

    /** @return array<string, array<string, array<string, mixed>>> */
    private static function environments(): array
    {
        /** @var array<string, array<string, array<string, mixed>>> $environments */
        $environments = config('horizon.environments', []);

        return $environments;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function supervisorsReading(string $environment, string $connection, string $queue): array
    {
        return array_filter(
            self::environments()[$environment] ?? [],
            static fn (array $supervisor): bool => ($supervisor['connection'] ?? null) === $connection
                && in_array($queue, (array) ($supervisor['queue'] ?? []), true),
        );
    }
}
