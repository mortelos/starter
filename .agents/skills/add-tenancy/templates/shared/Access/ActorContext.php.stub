<?php

declare(strict_types=1);

namespace App\Access;

use App\Models\Role;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Resolved person actor: a user acting within a tenant under a role.
 *
 * Mirrors Mortel\Access\ActorContext 1:1 (R4), except it references the host
 * App\Models\Role. Framework adoption is a find-replace of BOTH
 * `App\Access` -> `Mortel\Access` and `App\Models\Role` -> `Mortel\Models\Role`.
 */
final readonly class ActorContext implements AccessActor
{
    public function __construct(
        public ?Authenticatable $user,
        public string $tenantId,
        public string $branchId,
        public Role $role,
    ) {}

    public function userId(): string
    {
        $id = $this->user?->getAuthIdentifier();

        return is_scalar($id) ? (string) $id : '';
    }

    public function roleId(): string
    {
        return (string) $this->role->getKey();
    }
}
