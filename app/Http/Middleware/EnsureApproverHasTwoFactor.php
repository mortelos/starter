<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Contracts\GovernanceGate;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Requires approvers who signed in with a password to confirm two-factor authentication
 * before opening protected portal pages. Settings and logout remain reachable so they
 * can finish setup or sign out.
 */
final class EnsureApproverHasTwoFactor
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('starter.auth.require_two_factor_for_approvers', true)) {
            return $next($request);
        }

        if ($request->session()->get('auth.method') !== 'password') {
            return $next($request);
        }

        $user = $request->user();

        if (! $user instanceof User || $user->hasEnabledTwoFactorAuthentication()) {
            return $next($request);
        }

        if (! app(GovernanceGate::class)->canManage($user)) {
            return $next($request);
        }

        return redirect()
            ->route('settings')
            ->with('status', 'Stel eerst tweestaps in. Voor jouw rol is een extra inlogcode verplicht bij inloggen met een wachtwoord.');
    }
}
