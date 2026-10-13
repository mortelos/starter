<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Naam en e-mailadres van het eigen account. Alleen de gebruiker zelf; een
 * beheerder die andermans profiel wil wijzigen hoort daar een eigen Action en
 * ability voor te krijgen, niet deze.
 */
final class UpdateProfile
{
    public const ABILITY = 'account.update';

    public function handle(User $user, string $name, string $email, User $actor): void
    {
        if (! $actor->is($user)) {
            throw new AuthorizationException('Alleen de eigenaar van een account kan het profiel wijzigen.');
        }

        $user->update(['name' => $name, 'email' => $email]);
    }
}
