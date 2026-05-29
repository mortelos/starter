<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Default invitation accept handler.
 *
 * Replace with a real invitation flow tied to your tenant/membership model.
 * Defaults to 501 so the boot works while you decide on the invitation token
 * persistence layer.
 */
final class AcceptInvitationController extends Controller
{
    public function show(Request $request, string $token): View
    {
        abort(501, 'Invitation accept (GET) not yet implemented. Replace app/Http/Controllers/Auth/AcceptInvitationController.php.');
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        abort(501, 'Invitation accept (POST) not yet implemented. Replace app/Http/Controllers/Auth/AcceptInvitationController.php.');
    }
}
