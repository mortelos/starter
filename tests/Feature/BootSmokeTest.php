<?php

declare(strict_types=1);

use Illuminate\Support\Facades\View;

use function Pest\Laravel\artisan;
use function Pest\Laravel\get;

it('redirects the root to the login page for guests', function (): void {
    get('/')->assertRedirect('/login');
});

it('serves the login page', function (): void {
    get('/login')->assertOk();
});

it('throws LogicException when an auth controller is missing', function (): void {
    config()->set('starter.auth.controllers.password_login', null);

    expect(fn () => require base_path('routes/starter.php'))
        ->toThrow(LogicException::class, 'Missing starter route class config');
});

it('exposes the starter view namespaces and shell pages', function (): void {
    expect(View::exists('mortelos-starter::layouts.app'))->toBeTrue();
    expect(View::exists('layouts.guest'))->toBeTrue();

    expect(is_file(resource_path('views/components/auth/password-form.blade.php')))->toBeTrue();
    expect(is_file(resource_path('views/livewire/pages/dashboard/dashboard.blade.php')))->toBeTrue();
    expect(is_file(resource_path('views/livewire/pages/inbox/inbox.blade.php')))->toBeTrue();
    expect(is_file(resource_path('views/livewire/pages/auth/login.blade.php')))->toBeTrue();
});

it('reports the doctor command as green for the default config', function (): void {
    $command = artisan('starter:doctor');

    expect($command)->toBeInstanceOf(\Illuminate\Testing\PendingCommand::class);
    assert($command instanceof \Illuminate\Testing\PendingCommand);

    $command->assertSuccessful();
});
