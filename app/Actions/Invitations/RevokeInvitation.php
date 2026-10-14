<?php

declare(strict_types=1);

namespace App\Actions\Invitations;

use App\Access\StarterGovernanceGate;
use App\Aggregates\InvitationAggregate;
use App\Exceptions\InvitationNotOpenException;
use App\Models\Invitation;
use Illuminate\Auth\Access\AuthorizationException;
use Mortel\Actions\ActionResult;
use Mortel\Contracts\TenantResolver;
use Mortel\Support\Actor;
use Mortel\Support\EventContext;

/**
 * Revokes an open invitation; its link stops working. Also the counter-Action
 * of InviteUser. No Undo: a revoked link stays dead, so the way back is a new
 * invitation with a new link, not reviving the old one.
 */
final class RevokeInvitation
{
    public const ABILITY = 'invitations.manage';

    public function __construct(
        private readonly StarterGovernanceGate $gate,
        private readonly TenantResolver $tenantResolver,
    ) {}

    /** @throws AuthorizationException */
    public function handle(string $invitationId, Actor $actor): ActionResult
    {
        if (! $this->gate->allowsActor($actor, self::ABILITY)) {
            throw new AuthorizationException('Je mag geen uitnodigingen intrekken.');
        }

        $invitation = Invitation::query()->whereKey($invitationId)->where('tenant_id', $this->tenantResolver->id())->first();

        if ($invitation === null) {
            return ActionResult::failed(InvitationNotOpenException::unknown()->getMessage());
        }

        try {
            app(EventContext::class)->within(
                $actor,
                'Uitnodiging voor '.$invitation->email.' ingetrokken',
                fn () => InvitationAggregate::retrieve($invitation->id)->revoke(now()->toIso8601String())->persist(),
                $invitation->tenant_id,
            );
        } catch (InvitationNotOpenException $e) {
            return ActionResult::failed($e->getMessage());
        }

        return ActionResult::done('Uitnodiging voor '.$invitation->email.' ingetrokken.');
    }
}
