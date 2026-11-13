<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;

/**
 * Finish two-factor setup with the first code from the authenticator app. From then on the code is asked at login.
 */
final class ConfirmTwoFactor
{
    public const ABILITY = 'account.update';

    public function handle(User $user, string $code, User $actor): void
    {
        if (! $actor->is($user)) {
            throw new AuthorizationException('Alleen de eigenaar van een account kan tweestaps bevestigen.');
        }

        try {
            app(ConfirmTwoFactorAuthentication::class)($user, str_replace(' ', '', $code));
        } catch (ValidationException) {
            throw ValidationException::withMessages(['code' => 'Deze code klopt niet. Controleer de code in je app en probeer opnieuw.']);
        }
    }
}
