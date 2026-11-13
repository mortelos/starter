<?php

declare(strict_types=1);

namespace App\Support\ChangeLog;

use App\Actions\Invitations\RevokeInvitation;
use App\Contracts\ChangeLog\DescribesChanges;
use App\Events\ChangeLog\ChangeUndone;
use Mortel\Actions\Undo;

/**
 * The change log sentences of what the starter itself records: invitations, roles and policies, and the undo itself.
 * Tagged with ChangeLogDescriptions::TAG in StarterServiceProvider; an app tags its own class next to it
 * (see docs/change-log.md).
 */
final class StarterChangeDescriptions implements DescribesChanges
{
    /** Same window as the Undo in the result of InviteUser. */
    private const int INVITATION_UNDO_MINUTES = 60;

    public function register(ChangeLogDescriptions $descriptions): void
    {
        $descriptions->part('users', 'Gebruikers');
        $descriptions->part('access', 'Rollen en rechten');

        $descriptions->register('invitations.invitation.created', 'users', fn (ChangeLogEntry $entry): ChangeDescription => new ChangeDescription(
            sentence: 'Uitnodiging klaargezet voor '.($entry->text('email') ?? 'een onbekend adres'),
            undo: ($id = $entry->text('invitation_id')) === null ? null
                : Undo::of(RevokeInvitation::class, ['invitation_id' => $id], $entry->at->addMinutes(self::INVITATION_UNDO_MINUTES)),
        ));

        $descriptions->register('invitations.invitation.revoked', 'users', fn (ChangeLogEntry $entry): ChangeDescription => new ChangeDescription(
            sentence: 'Uitnodiging ingetrokken',
            detail: $entry->reason,
            noUndoReason: 'Een ingetrokken link blijft dood. Nodig opnieuw uit.',
        ));

        $descriptions->register('invitations.invitation.accepted', 'users', fn (ChangeLogEntry $entry): ChangeDescription => new ChangeDescription(
            sentence: 'Uitnodiging aangenomen',
            noUndoReason: 'Het account bestaat al. Haal iemand weg bij Gebruikers.',
        ));

        // Event type => [sentence with the name, sentence without it]
        $governance = [
            'role.created' => ['Rol ‘%s’ aangemaakt', 'Rol aangemaakt'],
            'role.updated' => ['Rol ‘%s’ aangepast', 'Rol aangepast'],
            'role.deleted' => ['Rol ‘%s’ verwijderd', 'Rol verwijderd'],
            'policy.created' => ['Recht ‘%s’ aangemaakt', 'Recht aangemaakt'],
            'policy.updated' => ['Recht ‘%s’ aangepast', 'Recht aangepast'],
            'policy.deleted' => ['Recht ‘%s’ verwijderd', 'Recht verwijderd'],
        ];

        foreach ($governance as $type => [$named, $plain]) {
            $descriptions->register($type, 'access', fn (ChangeLogEntry $entry): string => ($name = $entry->text('name')) === null ? $plain : sprintf($named, $name));
        }

        // The part of an undo row is the part of the change it undid (ChangeLogReader::partOf()), for rights and filters.
        $descriptions->register(ChangeUndone::TYPE, ChangeLogDescriptions::OTHER, fn (ChangeLogEntry $entry): ChangeDescription => new ChangeDescription(
            sentence: 'Ongedaan gemaakt: '.($entry->text('summary') ?? ChangeLogDescriptions::FALLBACK_SENTENCE),
        ));
    }
}
