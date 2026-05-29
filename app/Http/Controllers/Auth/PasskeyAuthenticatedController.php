<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Default passkey login handler.
 *
 * Replace with a real passkey verification (e.g. backed by
 * spatie/laravel-passkeys) when shipping. Defaults to a 501 so the boot
 * works while the host decides on a passkey library.
 */
final class PasskeyAuthenticatedController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        abort(501, 'Passkey authentication is not yet implemented in this host. Replace app/Http/Controllers/Auth/PasskeyAuthenticatedController.php with a real implementation.');
    }
}
