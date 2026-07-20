<?php

declare(strict_types=1);

namespace App\Support;

use App\Contracts\GovernanceGate;
use App\Models\User;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Mortel\Contracts\TenantResolver;
use RuntimeException;

final readonly class StarterUsersResolver
{
    private const MANAGE_USERS = 'users.manage';

    public function __construct(
        private GovernanceGate $gate,
        private TenantResolver $tenantResolver,
    ) {}

    public function canManage(): bool
    {
        $user = Auth::user();

        if (! $user instanceof User || ! $this->hasMember($this->userKey($user))) {
            return false;
        }

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
        $tenantId = $this->tenantId();

        if ($tenantId === null) {
            return [];
        }

        $members = [];

        foreach (User::query()
            ->select('users.*')
            ->addSelect([
                'tenant_user.role as membership_role',
                'tenant_user.created_at as membership_created_at',
            ])
            ->join('tenant_user', 'tenant_user.user_id', '=', 'users.id')
            ->where('tenant_user.tenant_id', $tenantId)
            ->orderBy('users.name')
            ->get() as $user) {
            $members[] = [
                'id' => $this->userKey($user),
                'name' => $user->name,
                'email' => $user->email,
                'role' => $this->membershipRole($user->getAttribute('membership_role')),
                'joined_at' => $this->membershipDate($user->getAttribute('membership_created_at')),
            ];
        }

        return $members;
    }

    public function hasMember(string $userId): bool
    {
        $tenantId = $this->tenantId();

        return $tenantId !== null
            && $userId !== ''
            && DB::table('tenant_user')
                ->where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->exists();
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

    private function membershipRole(mixed $role): string
    {
        $role = is_scalar($role) ? trim((string) $role) : '';

        return $role !== '' ? $role : 'Geen rol';
    }

    private function membershipDate(mixed $date): string
    {
        if ($date instanceof DateTimeInterface) {
            return $date->format('d-m-Y');
        }

        if (is_string($date) && $date !== '') {
            return CarbonImmutable::parse($date)->format('d-m-Y');
        }

        return 'Onbekend';
    }

    private function userKey(User $user): string
    {
        $key = $user->getKey();

        if (! is_int($key) && ! is_string($key)) {
            throw new RuntimeException('Expected a scalar user key.');
        }

        return (string) $key;
    }

    private function tenantId(): ?string
    {
        $tenantId = $this->tenantResolver->id();

        return is_string($tenantId) && $tenantId !== '' ? $tenantId : null;
    }
}
