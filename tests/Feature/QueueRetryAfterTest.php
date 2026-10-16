<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Mortel\Jobs\ContinueAgentRunJob;
use Mortel\Jobs\ExecuteAgentRunJob;
use Tests\Support\RetryAfterProbeJob;

uses(RefreshDatabase::class);

/**
 * A tries=1 job that runs longer than retry_after is handed to a second worker, which
 * fails it with MaxAttemptsExceededException while the first worker is still busy.
 * For the agent-run jobs that marks the run Failed and makes the first worker's
 * later complete() a no-op.
 */
it('does not hand a running tries=1 job to a second worker within the agent job timeout', function (): void {
    config(['queue.default' => 'database']);
    $queue = app('queue')->connection('database');

    $queue->pushOn('agents', new RetryAfterProbeJob);

    expect($queue->pop('agents')?->attempts())->toBe(1);

    Carbon::setTestNow(now()->addSeconds(agentJobTimeout()));

    expect($queue->pop('agents')?->getJobId())->toBeNull('worker B got the job while worker A may still be running it');
});

it('still retries a job whose worker is gone once retry_after has passed', function (): void {
    config(['queue.default' => 'database']);
    $queue = app('queue')->connection('database');

    $queue->pushOn('agents', new RetryAfterProbeJob);
    $queue->pop('agents');

    Carbon::setTestNow(now()->addSeconds(config()->integer('queue.connections.database.retry_after') + 1));

    expect($queue->pop('agents')?->attempts())->toBe(2);
});

it('keeps retry_after above the agent job timeout', function (string $connection): void {
    expect(config()->integer("queue.connections.{$connection}.retry_after"))->toBeGreaterThan(agentJobTimeout());
})->with(['database', 'redis']);

it('lets composer dev work the agents queue', function (): void {
    $composer = (string) file_get_contents(base_path('composer.json'));

    expect($composer)->toMatch('/queue:listen\b[^"]*--queue=[^ "]*\bagents\b/');
});

function agentJobTimeout(): int
{
    return max((new ExecuteAgentRunJob('guard'))->timeout, (new ContinueAgentRunJob('guard', 'guard'))->timeout);
}
