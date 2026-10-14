<?php

declare(strict_types=1);

namespace App\Events\Invitations;

use Mortel\Contracts\ProvidesStoredEventMetadata;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

final class InvitationAccepted extends ShouldBeStored implements ProvidesStoredEventMetadata
{
    public function __construct(
        public readonly string $invitation_id,
        public readonly string $user_id,
        public readonly string $accepted_at,
    ) {}

    public function storedEventType(): string
    {
        return 'invitations.invitation.accepted';
    }

    public function storedAggregateType(): string
    {
        return 'invitation';
    }

    public function description(): string
    {
        return 'Uitnodiging geaccepteerd';
    }
}
