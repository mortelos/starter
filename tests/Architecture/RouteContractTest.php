<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

it('throws LogicException when a required auth controller is missing', function (): void {
    config()->set('starter.auth.controllers.password_login', null);

    // Re-load the route file via the package; without a controller class, the
    // closure inside throws when /auth/login is registered.
    expect(fn () => require __DIR__.'/../../routes/starter.php')
        ->toThrow(LogicException::class, 'Missing starter route class config');
});

it('loads starter routes when auth controllers are filled', function (): void {
    config()->set('starter.auth.post_login_redirect_resolver', StubAction::class);
    config()->set('starter.auth.controllers.password_login', StubController::class);
    config()->set('starter.auth.controllers.passkey_authenticated', StubController::class);
    config()->set('starter.auth.controllers.accept_invitation', StubController::class);
    config()->set('starter.auth.controllers.tenant_select', StubController::class);

    Route::group([], function (): void {
        require __DIR__.'/../../routes/starter.php';
    });

    $names = collect(Route::getRoutes())->map->getName()->filter()->values()->all();

    expect($names)->toContain(
        'home',
        'login',
        'passkeys.login',
        'auth.password-login',
        'invite.show',
        'invite.store',
        'dashboard',
        'inbox',
        'governance',
        'users',
        'settings',
        'onboarding',
        'auth.tenant-select',
        'auth.tenant-store',
        'logout',
    );
});

final class StubController
{
    public function __invoke(): string
    {
        return 'ok';
    }

    public function show(): string
    {
        return 'show';
    }

    public function store(): string
    {
        return 'store';
    }
}

final class StubAction
{
    public function execute(): string
    {
        return '/dashboard';
    }
}
