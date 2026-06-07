<?php

declare(strict_types=1);

namespace App\Support;

use App\Contracts\GovernanceGate;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final readonly class StarterUsersResolver
{
    private const MANAGE_USERS = 'users.manage';

    public function __construct(
        private GovernanceGate $gate,
    ) {}

    public function canManage(): bool
    {
        $user = Auth::user();

        if (method_exists($this->gate, 'allows')) {
            return (bool) $this->gate->allows($user, self::MANAGE_USERS);
        }

        return $this->gate->canManage($user);
    }

    /**
     * @return list<array{id: string, name: string, email: string, role: string, joined_at: string}>
     */
    public function members(): array
    {
        $members = [];

        foreach (User::query()
            ->with('roles')
            ->orderBy('name')
            ->get() as $user) {
            $members[] = [
                'id' => $this->userKey($user),
                'name' => $user->name,
                'email' => $user->email,
                'role' => $this->roleLabel($user),
                'joined_at' => $user->created_at?->format('d-m-Y') ?? 'Onbekend',
            ];
        }

        return $members;
    }

    /**
     * @return list<array{id: string, email: string, role: string, expires_at: string}>
     */
    public function pendingInvites(): array
    {
        return [];
    }

    /**
     * @throws ValidationException
     */
    public function invite(string $email, string $role): void
    {
        throw ValidationException::withMessages([
            'email' => 'Uitnodigingen zijn nog niet gekoppeld aan een membershipmodel.',
        ]);
    }

    public function revokeInvite(string $inviteId): void
    {
        // No invitation store exists in the starter baseline.
    }

    private function roleLabel(User $user): string
    {
        $roles = $user->roles
            ->map(fn (Role $role): string => $role->name)
            ->filter(fn (string $role): bool => $role !== '')
            ->values();

        if ($roles->isEmpty()) {
            return 'Geen rol';
        }

        return implode(', ', $roles->all());
    }

    private function userKey(User $user): string
    {
        $key = $user->getKey();

        if (! is_int($key) && ! is_string($key)) {
            throw new RuntimeException('Expected a scalar user key.');
        }

        return (string) $key;
    }
}
