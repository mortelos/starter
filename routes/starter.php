<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

$starterClass = static function (string $key): string {
    $class = config($key);

    if (! is_string($class) || $class === '') {
        throw new LogicException("Missing starter route class config [{$key}].");
    }

    return $class;
};

Route::get('/', function () use ($starterClass) {
    if (! Auth::check()) {
        return redirect()->route('login');
    }

    $tenantId = session('tenant_id');

    if ($tenantId === null) {
        return redirect()->route('auth.tenant-select');
    }

    return redirect(app($starterClass('starter.auth.post_login_redirect_resolver'))
        ->execute(Auth::user(), $tenantId));
})->name('home');

Route::middleware(['web'])->group(function () use ($starterClass): void {
    Route::livewire('/login', 'starter::pages.auth.login')->name('login');

    Route::post('passkeys/authenticate', $starterClass('starter.auth.controllers.passkey_authenticated'))
        ->middleware('throttle:auth')
        ->name('passkeys.login');

    $passkeyAuthenticationOptionsController = config('starter.auth.controllers.passkey_authentication_options');

    if (is_string($passkeyAuthenticationOptionsController) && $passkeyAuthenticationOptionsController !== '') {
        Route::get('passkeys/authentication-options', $passkeyAuthenticationOptionsController)
            ->name('passkeys.authentication_options');
    }

    Route::post('/auth/login', $starterClass('starter.auth.controllers.password_login'))
        ->middleware('throttle:auth')
        ->name('auth.password-login');

    Route::get('/invite/{token}', [$starterClass('starter.auth.controllers.accept_invitation'), 'show'])->name('invite.show');
    Route::post('/invite/{token}', [$starterClass('starter.auth.controllers.accept_invitation'), 'store'])
        ->middleware('throttle:auth')
        ->name('invite.store');

    Route::middleware('auth')->group(function () use ($starterClass): void {
        Route::livewire('/onboarding', 'starter::pages.onboarding.onboarding')->name('onboarding');
        Route::livewire('/dashboard', 'starter::pages.dashboard.dashboard')->name('dashboard');
        Route::livewire('/inbox', 'starter::pages.inbox.inbox')->name('inbox');
        Route::livewire('/governance', 'starter::pages.governance.governance')->name('governance');
        Route::livewire('/users', 'starter::pages.users.users')->name('users');
        Route::livewire('/settings', 'starter::pages.settings.settings')->name('settings');

        Route::get('/auth/tenant-select', [$starterClass('starter.auth.controllers.tenant_select'), 'show'])
            ->name('auth.tenant-select');
        Route::post('/auth/tenant-select', [$starterClass('starter.auth.controllers.tenant_select'), 'store'])
            ->name('auth.tenant-store');

        Route::post('/logout', function () {
            Auth::logout();
            request()->session()->invalidate();
            request()->session()->regenerateToken();

            return redirect()->route('login');
        })->name('logout');
    });
});
