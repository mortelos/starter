<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * The current password as proof before a sensitive change to your own account (two-factor setup, for example).
 */
final class PasswordConfirmation
{
    public static function ensure(User $user, string $password, string $field = 'confirm_password'): void
    {
        if ($password === '' || ! Hash::check($password, (string) $user->password)) {
            throw ValidationException::withMessages([
                $field => 'Wachtwoord klopt niet.',
            ]);
        }
    }
}
