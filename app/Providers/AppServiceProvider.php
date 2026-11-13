<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Fortify serves the two-factor features only: its login, logout and password routes would sit next to
        // the starter's own. The two routes the starter needs are in routes/starter.php.
        Fortify::ignoreRoutes();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('auth', static fn (Request $request): Limit => Limit::perMinute(5)
            ->by($request->ip() ?? 'global'));

        // The code or recovery code after the password or after social login. Keyed on the user waiting in the
        // challenge (Fortify's `login.id`) and the IP, so one guessed code does not lock out someone else.
        RateLimiter::for('two-factor', static function (Request $request): Limit {
            $waiting = $request->session()->get('login.id');

            return Limit::perMinute(5)->by((is_scalar($waiting) ? (string) $waiting : '').'|'.($request->ip() ?? 'global'));
        });

        Fortify::twoFactorChallengeView(static fn () => View::make('auth.two-factor-challenge'));
    }
}
