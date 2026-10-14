<?php

declare(strict_types=1);

namespace App\Aggregates;

use App\Events\Invitations\InvitationAccepted;
use App\Events\Invitations\InvitationCreated;
use App\Events\Invitations\InvitationRevoked;
use App\Exceptions\InvitationNotOpenException;
use Carbon\CarbonImmutable;
use Spatie\EventSourcing\AggregateRoots\AggregateRoot;

/**
 * One invitation to the tenant. The aggregate holds the guards: whatever reads
 * the projection, only this class decides whether an invitation may still be
 * revoked or accepted.
 */
final class InvitationAggregate extends AggregateRoot
{
    private bool $created = false;

    private ?CarbonImmutable $expiresAt = null;

    private bool $revoked = false;

    private bool $accepted = false;

    public function create(
        string $orgId,
        string $branchId,
        string $email,
        string $roleId,
        string $invitedBy,
        string $tokenHash,
        string $expiresAt,
    ): static {
        $this->recordThat(new InvitationCreated(
            invitation_id: $this->uuid(),
            org_id: $orgId,
            branch_id: $branchId,
            email: $email,
            role_id: $roleId,
            invited_by: $invitedBy,
            token_hash: $tokenHash,
            expires_at: $expiresAt,
        ));

        return $this;
    }

    /** @throws InvitationNotOpenException */
    public function revoke(string $revokedAt): static
    {
        match (true) {
            ! $this->created => throw InvitationNotOpenException::unknown(),
            $this->accepted => throw InvitationNotOpenException::accepted(),
            $this->revoked => throw InvitationNotOpenException::revoked(),
            default => null,
        };

        $this->recordThat(new InvitationRevoked(invitation_id: $this->uuid(), revoked_at: $revokedAt));

        return $this;
    }

    /** @throws InvitationNotOpenException */
    public function accept(string $userId, string $acceptedAt): static
    {
        $this->ensureOpen($acceptedAt);

        $this->recordThat(new InvitationAccepted(invitation_id: $this->uuid(), user_id: $userId, accepted_at: $acceptedAt));

        return $this;
    }

    /**
     * Whether the invitation can still be accepted at $at. Public so an Action
     * can refuse before it does other work, such as creating the account.
     *
     * @throws InvitationNotOpenException
     */
    public function ensureOpen(string $at): void
    {
        match (true) {
            ! $this->created => throw InvitationNotOpenException::unknown(),
            $this->accepted => throw InvitationNotOpenException::accepted(),
            $this->revoked => throw InvitationNotOpenException::revoked(),
            $this->expiresAt === null || CarbonImmutable::parse($at)->gte($this->expiresAt) => throw InvitationNotOpenException::expired(),
            default => null,
        };
    }

    protected function applyInvitationCreated(InvitationCreated $event): void
    {
        $this->created = true;
        $this->expiresAt = CarbonImmutable::parse($event->expires_at);
    }

    protected function applyInvitationRevoked(InvitationRevoked $event): void
    {
        $this->revoked = true;
    }

    protected function applyInvitationAccepted(InvitationAccepted $event): void
    {
        $this->accepted = true;
    }
}
