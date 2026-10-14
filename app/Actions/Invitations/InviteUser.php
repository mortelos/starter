<?php

declare(strict_types=1);

namespace App\Actions\Invitations;

use App\Access\StarterGovernanceGate;
use App\Aggregates\InvitationAggregate;
use App\Models\Invitation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mortel\Actions\ActionResult;
use Mortel\Actions\Undo;
use Mortel\Contracts\TenantResolver;
use Mortel\Enums\PolicyEffect;
use Mortel\Models\Policy;
use Mortel\Models\Role;
use Mortel\Support\Actor;
use Mortel\Support\EventContext;
use RuntimeException;

/**
 * Invites an email address to the current tenant with a role. The plain token
 * only leaves this Action inside `accept_url`; the event and the projection
 * keep its sha256 hash. Reversible within the hour through RevokeInvitation.
 */
final class InviteUser
{
    public const ABILITY = 'invitations.manage';

    private const VALID_DAYS = 7;

    public function __construct(
        private readonly StarterGovernanceGate $gate,
        private readonly TenantResolver $tenantResolver,
    ) {}

    /** @throws AuthorizationException */
    public function handle(string $email, string $roleId, Actor $actor): ActionResult
    {
        if (! $this->gate->allowsActor($actor, self::ABILITY)) {
            throw new AuthorizationException('Je mag geen gebruikers uitnodigen.');
        }

        $email = Str::lower(trim($email));
        $tenantId = $this->tenantResolver->id() ?? throw new RuntimeException('No tenant resolved for this request.');

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return ActionResult::failed('Vul een geldig e-mailadres in.');
        }

        if (! Role::query()->whereKey($roleId)->where('org_id', $tenantId)->exists()) {
            return ActionResult::failed('Kies een rol die bij deze omgeving hoort.');
        }

        if (! $this->actorHoldsEveryAllowOf($actor, $roleId)) {
            return ActionResult::failed('Je kunt niemand uitnodigen voor een rol met meer rechten dan jijzelf.');
        }

        $isMember = DB::table('tenant_user')
            ->join('users', 'users.id', '=', 'tenant_user.user_id')
            ->where('tenant_user.tenant_id', $tenantId)
            ->whereRaw('lower(users.email) = ?', [$email])
            ->exists();

        if ($isMember) {
            return ActionResult::failed($email.' is al lid van deze omgeving.');
        }

        if (Invitation::query()->open()->where('tenant_id', $tenantId)->where('email', $email)->exists()) {
            return ActionResult::failed('Er staat al een uitnodiging open voor '.$email.'.');
        }

        $id = (string) Str::ulid();
        $token = Str::random(64);
        $branchId = self::branchId();

        app(EventContext::class)->within(
            $actor,
            'Uitnodiging voor '.$email,
            fn () => InvitationAggregate::retrieve($id)
                ->create(
                    orgId: $tenantId,
                    branchId: $branchId,
                    email: $email,
                    roleId: $roleId,
                    invitedBy: $actor->id(),
                    tokenHash: hash('sha256', $token),
                    expiresAt: now()->addDays(self::VALID_DAYS)->toIso8601String(),
                )
                ->persist(),
            $tenantId,
            $branchId,
        );

        return ActionResult::done(
            'Uitnodiging klaargezet voor '.$email.'.',
            ['invitation_id' => $id, 'accept_url' => route('invite.show', ['token' => $token])],
            Undo::of(RevokeInvitation::class, ['invitation_id' => $id], now()->addHour()),
        );
    }

    /**
     * Whether the actor itself holds every ability the role allows, each in the
     * scope of the policy that grants it. Without this check `invitations.manage`
     * alone would let anyone invite a second address of their own into the owner
     * role and so hand themselves rights they lack. It follows the same rule as
     * CheckPolicy: only the actions map counts, the effect column is not read.
     */
    private function actorHoldsEveryAllowOf(Actor $actor, string $roleId): bool
    {
        foreach (Policy::query()->where('role_id', $roleId)->get() as $policy) {
            if (! is_array($policy->actions)) {
                continue;
            }

            foreach ($policy->actions as $ability => $effect) {
                if ($effect === PolicyEffect::Allow->value && ! $this->gate->allowsActor($actor, (string) $ability, $policy->scope)) {
                    return false;
                }
            }
        }

        return true;
    }

    /** The branch every invitation event is recorded in; the three invitation Actions share it. */
    public static function branchId(): string
    {
        return is_string($branch = config('starter.tenancy.default_branch_id', 'main')) ? $branch : 'main';
    }
}
