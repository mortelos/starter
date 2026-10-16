<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Change your own password, with the current password as proof. Not
 * reversible (the old password is gone), so there is no Undo.
 */
final class ChangePassword
{
    public const ABILITY = 'account.update';

    public function handle(User $user, string $currentPassword, string $newPassword, User $actor): void
    {
        if (! $actor->is($user)) {
            throw new AuthorizationException('Alleen de eigenaar van een account kan het wachtwoord wijzigen.');
        }

        if (! Hash::check($currentPassword, (string) $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Huidige wachtwoord klopt niet.',
            ]);
        }

        $user->update(['password' => Hash::make($newPassword)]);
    }
}
