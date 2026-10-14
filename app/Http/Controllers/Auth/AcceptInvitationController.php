<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Invitations\AcceptInvitation;
use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Mortel\Support\Scalar;

/**
 * The page behind the invitation link: choose a name and password, then
 * AcceptInvitation creates the account and membership. The token only ever
 * comes from the URL.
 */
final class AcceptInvitationController extends Controller
{
    public function show(Request $request, string $token): View
    {
        $invitation = Invitation::query()->where('token_hash', hash('sha256', $token))->first() ?? abort(404);

        return view('auth.accept-invitation', [
            'token' => $token,
            'email' => $invitation->email,
            'isOpen' => Invitation::query()->open()->whereKey($invitation->id)->exists(),
        ]);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'confirmed', 'min:8'],
        ]);

        $result = app(AcceptInvitation::class)->handle($token, $request->string('name')->toString(), $request->string('password')->toString());

        if ($result->isFailed()) {
            return back()->withErrors(['email' => $result->message])->withInput($request->only('name'));
        }

        Auth::login(User::query()->findOrFail(Scalar::string($result->data['user_id'])));
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }
}
