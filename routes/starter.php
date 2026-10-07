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

    return redirect(app($starterClass('starter.auth.post_login_redirect_resolver'))
        ->handle(Auth::user()));
})->name('home');

Route::middleware(['web'])->group(function () use ($starterClass): void {
    Route::livewire('/login', 'pages::auth.login')->name('login');

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

    Route::middleware('auth')->group(function (): void {
        Route::livewire('/onboarding', 'pages::onboarding.index')->name('onboarding');
        Route::livewire('/dashboard', 'pages::dashboard.index')->name('dashboard');
        Route::livewire('/inbox', 'pages::inbox.index')->name('inbox');
        Route::livewire('/governance', 'pages::governance.index')->name('governance');
        Route::livewire('/governance/roles', 'pages::governance.roles')->name('governance.roles');
        Route::livewire('/users', 'pages::users.index')->name('users');
        Route::livewire('/settings', 'pages::settings.index')->name('settings');

        Route::post('/logout', function () {
            Auth::logout();
            request()->session()->invalidate();
            request()->session()->regenerateToken();

            return redirect()->route('login');
        })->name('logout');
    });
});
