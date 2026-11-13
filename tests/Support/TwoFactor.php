<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;

/*
 * Test helpers for two-factor authentication, in Fortify's own storage format: the secret and the recovery codes
 * are encrypted with the app key, the setup counts once `two_factor_confirmed_at` is set.
 */

/** @return list<string> */
function twoFactorRecoveryCodes(): array
{
    return ['aaaaaaaaaa-1111111111', 'bbbbbbbbbb-2222222222', 'cccccccccc-3333333333'];
}

/** A user whose password is "password", with two-factor set up and confirmed. */
function userWithTwoFactor(bool $confirmed = true): User
{
    $user = User::factory()->create();

    $user->forceFill([
        'two_factor_secret' => Fortify::currentEncrypter()->encrypt((new Google2FA)->generateSecretKey()),
        'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(json_encode(twoFactorRecoveryCodes(), JSON_THROW_ON_ERROR)),
        'two_factor_confirmed_at' => $confirmed ? now() : null,
    ])->save();

    return $user;
}

/** The code the authenticator app shows right now. */
function twoFactorCode(User $user): string
{
    $secret = Fortify::currentEncrypter()->decrypt((string) $user->two_factor_secret);

    return (new Google2FA)->getCurrentOtp(is_string($secret) ? $secret : '');
}
