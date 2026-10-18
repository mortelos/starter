<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
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

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $request->session()->regenerate();

        // Passport (and the auth middleware) stores the page the person came from, e.g. the OAuth consent page.
        // For a non-GET request Laravel takes that URL from the Referer header, which any other site can set,
        // so only follow it when it points at this app.
        $intended = $request->session()->get('url.intended');

        if (is_string($intended) && parse_url($intended, PHP_URL_HOST) !== $request->getHost()) {
            $request->session()->forget('url.intended');
        }

        return redirect()->intended(route('home'));
    }
}
