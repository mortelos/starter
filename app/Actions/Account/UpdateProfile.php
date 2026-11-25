<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Name and email address of your own account. Only the user themselves; an
 * admin who wants to change someone else's profile needs a separate Action and
 * ability for that, not this one.
 */
final class UpdateProfile
{
    public const ABILITY = 'account.update';

    /** @return array<string, array<int, mixed>> */
    public static function rules(User $user): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
        ];
    }

    public function handle(User $user, string $name, string $email, User $actor): void
    {
        if (! $actor->is($user)) {
            throw new AuthorizationException('Alleen de eigenaar van een account kan het profiel wijzigen.');
        }

        $email = mb_strtolower($email);

        Validator::make([
            'name' => $name,
            'email' => $email,
        ], self::rules($user))->validate();

        $user->update(['name' => $name, 'email' => $email]);
    }
}
