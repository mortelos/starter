<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Stub controller published by mortelos/starter.
 *
 * Replace with a real invitation handler tied to your tenant/membership
 * model. Contract:
 *
 *   show(Request $request, string $token): View
 *     Look up the invitation by token; if valid, render the accept form.
 *
 *   store(Request $request, string $token): RedirectResponse
 *     Validate inputs, create the user + membership, log them in,
 *     redirect to the tenant-select or dashboard.
 */
final class AcceptInvitationController extends Controller
{
    public function show(Request $request, string $token): View
    {
        abort(501, 'Invitation accept (GET) not yet implemented. Replace the published stub.');
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        abort(501, 'Invitation accept (POST) not yet implemented. Replace the published stub.');
    }
}
