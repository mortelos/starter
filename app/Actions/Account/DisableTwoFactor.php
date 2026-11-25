<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Contracts\GovernanceGate;
use App\Models\User;
use App\Support\PasswordConfirmation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;

/**
 * Turn two-factor off for your own account, with the password as proof. The secret and the recovery codes are
 * deleted, so turning it on again starts from scratch. Not reversible, so there is no Undo. A setup that was never
 * confirmed protects nothing yet; cancelling it needs no password.
 */
final class DisableTwoFactor
{
    public const ABILITY = 'account.update';

    public function handle(User $user, string $password, User $actor): void
    {
        if (! $actor->is($user)) {
            throw new AuthorizationException('Alleen de eigenaar van een account kan tweestaps uitzetten.');
        }

        if ($user->two_factor_confirmed_at !== null) {
            $rawPassword = $user->getRawOriginal('password');

            if (
                (bool) config('starter.auth.require_two_factor_for_approvers', true)
                && is_string($rawPassword)
                && $rawPassword !== ''
                && app(GovernanceGate::class)->canManage($user)
            ) {
                throw ValidationException::withMessages([
                    'confirm_password' => 'Goedkeurders met een wachtwoord kunnen tweestaps niet uitzetten.',
                ]);
            }

            PasswordConfirmation::ensure($user, $password);
        }

        app(DisableTwoFactorAuthentication::class)($user);
    }
}
