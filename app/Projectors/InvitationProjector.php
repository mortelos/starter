<?php

declare(strict_types=1);

namespace App\Projectors;

use App\Events\Invitations\InvitationAccepted;
use App\Events\Invitations\InvitationCreated;
use App\Events\Invitations\InvitationRevoked;
use App\Models\Invitation;
use Spatie\EventSourcing\EventHandlers\Projectors\Projector;

/**
 * Writes the `invitations` table, and nothing else does. Every column,
 * timestamps included, comes from the events, so a replay rebuilds the
 * same rows.
 */
final class InvitationProjector extends Projector
{
    public function onInvitationCreated(InvitationCreated $event): void
    {
        Invitation::query()->create([
            'id' => $event->invitation_id,
            'tenant_id' => $event->org_id,
            'email' => $event->email,
            'role_id' => $event->role_id,
            'invited_by' => $event->invited_by,
            'token_hash' => $event->token_hash,
            'expires_at' => $event->expires_at,
            'created_at' => $event->createdAt(),
            'updated_at' => $event->createdAt(),
        ]);
    }

    public function onInvitationRevoked(InvitationRevoked $event): void
    {
        Invitation::query()->findOrFail($event->invitation_id)->update([
            'revoked_at' => $event->revoked_at,
            'updated_at' => $event->revoked_at,
        ]);
    }

    public function onInvitationAccepted(InvitationAccepted $event): void
    {
        Invitation::query()->findOrFail($event->invitation_id)->update([
            'accepted_at' => $event->accepted_at,
            'accepted_user_id' => $event->user_id,
            'updated_at' => $event->accepted_at,
        ]);
    }

    public function resetState(?string $aggregateUuid = null): void
    {
        Invitation::query()->when($aggregateUuid !== null, fn ($query) => $query->whereKey($aggregateUuid))->delete();
    }
}
