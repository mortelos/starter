<?php

declare(strict_types=1);

use App\Http\Middleware\SerializeTwoFactorChallenge;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\TwoFactorAuthenticatedSessionController;

$starterClass = static function (string $key): string {
    $class = config($key);

    if (! is_string($class) || $class === '') {
        throw new LogicException("Missing starter route class config [{$key}].");
    }

    return $class;
};

Route::get('/', function (Request $request) use ($starterClass) {
    if (! Auth::check()) {
        return redirect()->route('login');
    }

    // mortelos/social-login sends a refused link home with the reason (its `redirect` is one setting for both
    // signing in and linking). Show the reason where linking starts, not on a dashboard that never reads it.
    $refusal = $request->session()->get('errors')?->getBag('default')->first('social_login');

    if (is_string($refusal) && $refusal !== '') {
        return redirect()->route('settings')->withErrors(['social_login' => $refusal]);
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

    // Laravel Fortify's code challenge, after the password (PasswordLoginController) or after social login
    // (mortelos/social-login). Fortify's own routes are off (AppServiceProvider); these two are the only ones used.
    // The POST checks one code at a time per waiting user, so one recovery code cannot be accepted twice at once.
    Route::get('/two-factor-challenge', [TwoFactorAuthenticatedSessionController::class, 'create'])
        ->middleware('guest:'.config('fortify.guard'))
        ->name('two-factor.login');
    Route::post('/two-factor-challenge', [TwoFactorAuthenticatedSessionController::class, 'store'])
        ->middleware(['guest:'.config('fortify.guard'), 'throttle:two-factor', SerializeTwoFactorChallenge::class])
        ->name('two-factor.login.store');

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

        // Link Google or Microsoft to your own account: the package's link route, with the settings page as the
        // place to come back to. Linking itself (and its refusals) is the package's.
        Route::get('/settings/social-login/{provider}', function (Request $request, string $provider) {
            $request->session()->put('url.intended', route('settings'));

            return redirect()->route('social-login.link', ['provider' => $provider]);
        })->where('provider', '[a-z]+')->name('settings.social-login.link');

        Route::post('/logout', function () {
            Auth::logout();
            request()->session()->invalidate();
            request()->session()->regenerateToken();

            return redirect()->route('login');
        })->name('logout');
    });
});
