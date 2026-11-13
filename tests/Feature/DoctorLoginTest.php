<?php

declare(strict_types=1);

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\PendingCommand;
use Tests\TestCase;

use function Pest\Laravel\artisan;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    /** @var TestCase $this */
    $this->seed(DatabaseSeeder::class);
});

it('reports what stays off when no social login provider has its keys', function (): void {
    config(['social-login.providers' => ['google'], 'social-login.google.client_id' => null, 'social-login.google.client_secret' => null]);

    $command = artisan('starter:doctor');
    assert($command instanceof PendingCommand);
    $command
        ->expectsOutputToContain('no social login provider has its client id and secret')
        ->expectsOutputToContain('social login provider google is listed but has no client id and secret: no button')
        ->assertSuccessful();
});

it('lists the social login buttons that are set up', function (): void {
    config(['social-login.providers' => ['google'], 'social-login.google.client_id' => 'id', 'social-login.google.client_secret' => 'secret']);

    $command = artisan('starter:doctor');
    assert($command instanceof PendingCommand);
    $command->expectsOutputToContain('social login buttons for: google')->assertSuccessful();
});

it('fails when social login lists a provider that does not exist', function (): void {
    config(['social-login.providers' => ['facebook']]);

    $command = artisan('starter:doctor');
    assert($command instanceof PendingCommand);
    $command->assertFailed();
});

it('fails when the Fortify guard is not the default guard', function (): void {
    config(['fortify.guard' => 'api']);

    $command = artisan('starter:doctor');
    assert($command instanceof PendingCommand);
    $command->expectsOutputToContain('fortify.guard must be the default guard')->assertFailed();
});

it('fails when two-factor is not enabled in the Fortify features', function (): void {
    config(['fortify.features' => []]);

    $command = artisan('starter:doctor');
    assert($command instanceof PendingCommand);
    $command->assertFailed();
});

it('fails when the social login table is missing', function (): void {
    Schema::dropIfExists('social_identities');

    $command = artisan('starter:doctor');
    assert($command instanceof PendingCommand);
    $command->assertFailed();
});
