<?php

declare(strict_types=1);

use App\Contracts\ChangeLog\ChangeLogArchive;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\PendingCommand;
use Tests\TestCase;

use function Pest\Laravel\artisan;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    /** @var TestCase $this */
    $this->seed(DatabaseSeeder::class);
});

it('names the change log outlets and stays green', function (): void {
    $command = artisan('starter:doctor');
    assert($command instanceof PendingCommand);
    $command->expectsOutputToContain('change log outlet ChangeLogArchive → App\Support\ChangeLog\NoChangeLogArchive')->assertSuccessful();
});

it('fails when a change log outlet cannot be resolved', function (): void {
    app()->bind(ChangeLogArchive::class, fn () => throw new RuntimeException('broken binding'));

    $command = artisan('starter:doctor');
    assert($command instanceof PendingCommand);
    $command->expectsOutputToContain('ChangeLogArchive cannot be resolved: broken binding')->assertFailed();
});
