<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Stub controller published by mortelos/starter.
 *
 * Replace with a real passkey authentication handler (e.g. backed by
 * spatie/laravel-passkeys). The contract: verify the passkey assertion,
 * log the user in, and redirect to the tenant-select route.
 *
 * For a working passkey reference, see UteqOS:
 *   app/Http/Controllers/Auth/PasskeyAuthenticatedController.php
 */
final class PasskeyAuthenticatedController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        abort(501, 'Passkey authentication is not yet implemented in this host. Replace the published stub at app/Http/Controllers/Auth/PasskeyAuthenticatedController.php.');
    }
}
