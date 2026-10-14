<?php

declare(strict_types=1);

namespace App\Events\Invitations;

use Mortel\Contracts\ProvidesStoredEventMetadata;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

/**
 * Someone was invited to the tenant. Only the sha256 hash of the token is
 * stored; the plain token exists once, in the link the inviter hands out.
 */
final class InvitationCreated extends ShouldBeStored implements ProvidesStoredEventMetadata
{
    public function __construct(
        public readonly string $invitation_id,
        public readonly string $org_id,
        public readonly string $branch_id,
        public readonly string $email,
        public readonly string $role_id,
        public readonly string $invited_by,
        public readonly string $token_hash,
        public readonly string $expires_at,
    ) {}

    public function storedEventType(): string
    {
        return 'invitations.invitation.created';
    }

    public function storedAggregateType(): string
    {
        return 'invitation';
    }

    public function description(): string
    {
        return 'Uitnodiging voor '.$this->email.' klaargezet';
    }
}
