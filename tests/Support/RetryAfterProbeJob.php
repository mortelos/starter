<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Throwable;

/**
 * Stand-in for the agent-run jobs: one try, queue `agents`. failed() is what
 * ExecuteAgentRunJob uses to mark the agent run Failed.
 */
final class RetryAfterProbeJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public static ?string $failedWith = null;

    public function __construct()
    {
        $this->onQueue('agents');
    }

    public function handle(): void {}

    public function failed(Throwable $exception): void
    {
        self::$failedWith = $exception::class;
    }
}
