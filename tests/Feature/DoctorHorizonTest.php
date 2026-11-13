<?php

declare(strict_types=1);

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\PendingCommand;
use Tests\TestCase;

use function Pest\Laravel\artisan;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    /** @var TestCase $this */
    $this->seed(DatabaseSeeder::class);
});

it('says Horizon is off while the queue is not Redis, and stays green', function (): void {
    config(['queue.default' => 'database']);

    $command = artisan('starter:doctor');
    assert($command instanceof PendingCommand);
    $command->expectsOutputToContain('Horizon and its dashboard are off')->assertSuccessful();
});

it('fails on Redis when the supervisors of the environment are missing', function (): void {
    config(['queue.default' => 'redis', 'horizon.environments' => []]);

    $command = artisan('starter:doctor');
    assert($command instanceof PendingCommand);
    $command->expectsOutputToContain('has no supervisors for the environment')->assertFailed();
});

it('fails on Redis when Redis does not answer', function (): void {
    config(['queue.default' => 'redis', 'horizon.environments.testing' => config('horizon.environments.production')]);
    Queue::shouldReceive('connection')->andThrow(new RuntimeException('connection refused'));

    $command = artisan('starter:doctor');
    assert($command instanceof PendingCommand);
    $command->expectsOutputToContain('Redis does not answer')->assertFailed();
});

it('is green on Redis when everything is there', function (): void {
    config(['queue.default' => 'redis', 'horizon.environments.testing' => config('horizon.environments.production')]);
    Queue::shouldReceive('connection->size')->andReturn(0);

    $command = artisan('starter:doctor');
    assert($command instanceof PendingCommand);
    $command->expectsOutputToContain('Redis answers')->assertSuccessful();
});
