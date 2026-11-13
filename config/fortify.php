<?php

declare(strict_types=1);

use Laravel\Fortify\Features;

/*
 * Laravel Fortify, used for the two-factor features only (docs/two-factor.md). The starter keeps its own login,
 * password and invitation flow; Fortify's routes are switched off (Fortify::ignoreRoutes() in
 * AppServiceProvider) and routes/starter.php registers the two it needs: the code challenge after the password
 * or after social login.
 */
return [

    // Must stay the default guard: mortelos/social-login hands a social sign-in over to Fortify's challenge and
    // throws a LogicException when the guards differ.
    'guard' => 'web',

    'passwords' => 'users',

    'username' => 'email',

    'email' => 'email',

    'lowercase_usernames' => true,

    // Where to go after the code; the home route sends the user to the post-login redirect.
    'home' => '/',

    'prefix' => '',

    'domain' => null,

    'middleware' => ['web'],

    // `login` is not used (the starter has its own login throttle, `auth`); `two-factor` is defined in AppServiceProvider.
    'limiters' => [
        'two-factor' => 'two-factor',
    ],

    // The challenge page is a starter view, registered in AppServiceProvider.
    'views' => true,

    'features' => [
        // `confirm`: a new setup counts only after the user entered a first code (two_factor_confirmed_at).
        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]),
    ],

];
