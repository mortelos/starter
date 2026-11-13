<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

final class PasswordLoginController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        /** @var array<string, mixed> $credentials */
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $remember = $request->boolean('remember');

        // Not Auth::attempt(): with two-factor on, a correct password is not yet a login. The user waits in
        // Fortify's challenge (`login.id`, `login.remember`) and is signed in by its code check, so the Login
        // event fires once, after the code.
        $guard = Auth::guard();
        $provider = $guard->getProvider();
        $user = $provider->retrieveByCredentials($credentials);

        if ($user === null || ! $provider->validateCredentials($user, $credentials)) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $provider->rehashPasswordIfRequired($user, $credentials);

        if ($user instanceof User && $user->hasEnabledTwoFactorAuthentication()) {
            $request->session()->regenerate();
            $request->session()->put([
                'login.id' => $user->getKey(),
                'login.remember' => $remember,
            ]);

            return redirect()->route('two-factor.login');
        }

        $guard->login($user, $remember);
        $request->session()->regenerate();

        return redirect()->route('home');
    }
}
