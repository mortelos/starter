<?php

declare(strict_types=1);

namespace App\Support;

use App\Actions\Invitations\InviteUser;
use App\Models\Invitation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Mortel\Contracts\TenantInvitationService;
use Mortel\Models\Role;
use Mortel\Support\Actor;

/**
 * The framework's invitation contract on top of InviteUser. It reads and calls
 * the Action; it writes nothing itself. `$role` may be a role id or a role
 * name in the tenant.
 */
final readonly class StarterTenantInvitationService implements TenantInvitationService
{
    public function __construct(private InviteUser $inviteUser) {}

    /** @throws ValidationException */
    public function invite(Model $tenant, Model $invitedBy, string $email, string $role): Model
    {
        $roleId = Role::query()
            ->where('org_id', $tenant->getKey())
            ->where(fn ($query) => $query->whereKey($role)->orWhere('name', $role))
            ->value('id');

        $result = $this->inviteUser->handle($email, is_string($roleId) ? $roleId : $role, Actor::user($invitedBy));

        if (! $result->isDone()) {
            throw ValidationException::withMessages(['email' => $result->message]);
        }

        return Invitation::query()->whereKey($result->data['invitation_id'])->firstOrFail();
    }
}
