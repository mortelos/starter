<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Name and email address of your own account. Only the user themselves; an
 * admin who wants to change someone else's profile needs a separate Action and
 * ability for that, not this one.
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
