<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Models\User;
use App\Support\PasswordConfirmation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;

/**
 * Replace all recovery codes of your own account with new ones, with the password as proof. The old codes stop
 * working at once.
 */
final class RegenerateRecoveryCodes
{
    public const ABILITY = 'account.update';

    public function handle(User $user, string $password, User $actor): void
    {
        if (! $actor->is($user)) {
            throw new AuthorizationException('Alleen de eigenaar van een account kan herstelcodes vernieuwen.');
        }

        PasswordConfirmation::ensure($user, $password);

        if (! $user->hasEnabledTwoFactorAuthentication()) {
            throw ValidationException::withMessages(['confirm_password' => 'Tweestaps staat niet aan.']);
        }

        app(GenerateNewRecoveryCodes::class)($user);
    }
}
