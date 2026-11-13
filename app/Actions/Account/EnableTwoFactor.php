<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Models\User;
use App\Support\PasswordConfirmation;
use Illuminate\Auth\Access\AuthorizationException;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;

/**
 * Start two-factor setup for your own account: Fortify's secret and recovery codes, with the password as proof.
 * The setup only counts once ConfirmTwoFactor has seen a first code (`two_factor_confirmed_at`), so a login is
 * not locked by a half-finished setup. Running it again while a setup is unconfirmed keeps the same secret.
 */
final class EnableTwoFactor
{
    public const ABILITY = 'account.update';

    public function handle(User $user, string $password, User $actor): void
    {
        if (! $actor->is($user)) {
            throw new AuthorizationException('Alleen de eigenaar van een account kan tweestaps instellen.');
        }

        PasswordConfirmation::ensure($user, $password);

        app(EnableTwoFactorAuthentication::class)($user);
    }
}
