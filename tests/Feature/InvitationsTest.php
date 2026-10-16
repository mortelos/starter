<?php

declare(strict_types=1);

use App\Access\StarterGovernanceGate;
use App\Actions\Invitations\AcceptInvitation;
use App\Actions\Invitations\InviteUser;
use App\Actions\Invitations\RevokeInvitation;
use App\Events\Invitations\InvitationCreated;
use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\User;
use App\Projectors\InvitationProjector;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mortel\Actions\ActionResult;
use Mortel\Actions\InviteUserToTenant;
use Mortel\Actions\Policy\CreatePolicy;
use Mortel\Actions\UndoRegistry;
use Mortel\Enums\PolicyEffect;
use Mortel\Exceptions\InvalidEventPayloadException;
use Mortel\Models\Role;
use Mortel\Support\Actor;
use Mortel\Support\EventContext;
use Spatie\EventSourcing\Projectionist;
use Spatie\EventSourcing\StoredEvents\Repositories\StoredEventRepository;

uses(RefreshDatabase::class);

function ownerRole(): Role
{
    return Role::query()->where('name', 'owner')->firstOrFail();
}

function invite(User $owner, string $email = 'nieuw@example.test'): ActionResult
{
    return app(InviteUser::class)->handle($email, modelKeyString(ownerRole()), Actor::user($owner));
}

/** The plain token from the accept_url of a successful invitation. */
function inviteToken(ActionResult $result): string
{
    $url = $result->data['accept_url'];

    return is_string($url) ? Str::afterLast($url, '/') : '';
}

/**
 * The stored event row (table `events`) of this type for this invitation.
 *
 * @return array<mixed>
 */
function storedInvitationEvent(string $invitationId, string $type): array
{
    return (array) DB::table('events')->where('aggregate_uuid', $invitationId)->where('event_type', 'invitations.invitation.'.$type)->sole();
}

function invitationId(ActionResult $result): string
{
    $id = $result->data['invitation_id'];

    return is_string($id) ? $id : '';
}

it('records an invitation as a stored event and projects it without the plain token', function (): void {
    $owner = invitationsManager();

    $result = invite($owner);

    expect($result->isDone())->toBeTrue()
        ->and($result->undo?->action)->toBe(RevokeInvitation::class)
        ->and($result->data['accept_url'])->toBe(route('invite.show', ['token' => inviteToken($result)]));

    $event = storedInvitationEvent(invitationId($result), 'created');
    expect($event['event_class'])->toBe('invitations.invitation.created')
        ->and($event['event_type'])->toBe('invitations.invitation.created')
        ->and($event['actor'])->toBe(modelKeyString($owner))
        ->and($event['org_id'])->toBe(governanceTenantId())
        ->and($event['branch_id'])->toBe('main');

    $row = Invitation::query()->findOrFail(invitationId($result));
    expect($row->token_hash)->toBe(hash('sha256', inviteToken($result)))
        ->and(json_encode($row->getAttributes()))->not->toContain(inviteToken($result))
        ->and($row->email)->toBe('nieuw@example.test');
});

it('refuses to invite without the ability', function (): void {
    $user = User::factory()->create();
    attachGovernanceMembership($user, createRole('owner'));

    invite($user);
})->throws(AuthorizationException::class);

/** A user with a role of their own, 'inviter', that is only allowed invitations.manage, next to a full owner role. */
function inviterBesideOwnerRole(): User
{
    $owner = createRole('owner');
    grantPolicy($owner, 'governance.manage');
    grantPolicy($owner, 'users.manage');
    grantPolicy($owner, 'invitations.manage');

    $inviter = User::factory()->create();
    $role = createRole('inviter');
    grantPolicy($role, 'invitations.manage');
    attachGovernanceMembership($inviter, $role);

    return $inviter;
}

it('refuses to invite into a role with more rights than the inviter', function (): void {
    $inviter = inviterBesideOwnerRole();

    $result = invite($inviter);

    expect($result->isFailed())->toBeTrue()
        ->and($result->message)->toBe('Je kunt niemand uitnodigen voor een rol met meer rechten dan jijzelf.')
        ->and(DB::table('events')->where('event_type', 'invitations.invitation.created')->exists())->toBeFalse()
        ->and(Invitation::query()->exists())->toBeFalse();
});

it('counts an allow in the actions map even when the policy effect says deny', function (): void {
    $inviter = inviterBesideOwnerRole();
    $role = createRole('mislabeled');
    app(CreatePolicy::class)->handle(
        name: 'governance.manage',
        description: null,
        scope: StarterGovernanceGate::scopeFor('governance.manage'),
        resourceType: null,
        resourceId: null,
        roleId: modelKeyString($role),
        actions: ['governance.manage' => 'allow'],
        effect: PolicyEffect::Deny,
        priority: 0,
        conditions: null,
        orgId: governanceTenantId(),
        branchId: governanceBranchId(),
        actor: 'system:test',
    );

    $result = app(InviteUser::class)->handle('nieuw@example.test', modelKeyString($role), Actor::user($inviter));

    expect($result->isFailed())->toBeTrue()
        ->and($result->message)->toBe('Je kunt niemand uitnodigen voor een rol met meer rechten dan jijzelf.')
        ->and(DB::table('events')->where('event_type', 'invitations.invitation.created')->exists())->toBeFalse();
});

it('invites into a role with no more rights than the inviter', function (): void {
    $inviter = inviterBesideOwnerRole();
    $sameRights = Role::query()->where('name', 'inviter')->firstOrFail();
    $onlyDenies = createRole('reader');
    grantPolicy($onlyDenies, 'governance.manage', PolicyEffect::Deny);

    $handle = fn (Role $role, string $email): ActionResult => app(InviteUser::class)
        ->handle($email, modelKeyString($role), Actor::user($inviter));

    expect($handle($sameRights, 'collega@example.test')->isDone())->toBeTrue()
        ->and($handle($onlyDenies, 'reader@example.test')->isDone())->toBeTrue()
        ->and($handle(createRole('empty'), 'empty@example.test')->isDone())->toBeTrue();
});

it('lets the owner invite into the owner role', function (): void {
    $owner = User::factory()->create();
    $role = createRole('owner');
    foreach (['governance.manage', 'users.manage', 'invitations.manage'] as $ability) {
        grantPolicy($role, $ability);
    }
    attachGovernanceMembership($owner, $role, 'admin');

    expect(invite($owner)->isDone())->toBeTrue();
});

it('stores an agent actor of 32 characters in a column wide enough for it', function (): void {
    $owner = invitationsManager();
    $actor = Actor::agent((string) Str::ulid(), $owner);

    $result = app(InviteUser::class)->handle('nieuw@example.test', modelKeyString(ownerRole()), $actor);

    expect(strlen($actor->id()))->toBe(32)
        ->and(storedInvitationEvent(invitationId($result), 'created')['actor'])->toBe($actor->id())
        ->and(Schema::getColumnType('events', 'actor', fullDefinition: true))->toBe(match (DB::getDriverName()) {
            'sqlite' => 'varchar', // SQLite does not keep a length; MySQL and Postgres do.
            'pgsql' => 'character varying(255)',
            default => 'varchar(255)',
        });
});

it('refuses to invite a member or an address that already has an open invitation', function (): void {
    $owner = invitationsManager();

    expect(invite($owner, $owner->email)->isFailed())->toBeTrue()
        ->and(invite($owner)->isDone())->toBeTrue()
        ->and(invite($owner, 'NIEUW@example.test')->isFailed())->toBeTrue();
});

it('revokes an open invitation once', function (): void {
    $owner = invitationsManager();
    $id = invitationId(invite($owner));
    app(EventContext::class)->reset(); // a new request: the setup leaves no org or branch behind

    $result = app(RevokeInvitation::class)->handle($id, Actor::user($owner));

    expect($result->isDone())->toBeTrue()
        ->and($result->undo)->toBeNull()
        ->and(storedInvitationEvent($id, 'revoked')['actor'])->toBe(modelKeyString($owner))
        ->and(storedInvitationEvent($id, 'revoked')['org_id'])->toBe(governanceTenantId())
        ->and(storedInvitationEvent($id, 'revoked')['branch_id'])->toBe('main')
        ->and(Invitation::query()->findOrFail($id)->revoked_at)->not->toBeNull()
        ->and(app(RevokeInvitation::class)->handle($id, Actor::user($owner))->isFailed())->toBeTrue();
});

it('undoes an invitation through the undo registry', function (): void {
    $owner = invitationsManager();
    $result = invite($owner);

    $undone = app(UndoRegistry::class)->undo($result->undo ?? throw new RuntimeException('No undo.'), Actor::user($owner));

    expect($undone->isDone())->toBeTrue()
        ->and(Invitation::query()->findOrFail(invitationId($result))->revoked_at)->not->toBeNull();
});

it('accepts an invitation into an account with a membership', function (): void {
    $owner = invitationsManager();
    $invited = invite($owner);
    app(EventContext::class)->reset(); // a new request: the setup leaves no org or branch behind

    $result = app(AcceptInvitation::class)->handle(inviteToken($invited), 'Nieuwe Collega', 'secret-password');

    expect($result->isDone())->toBeTrue();

    $user = User::query()->where('email', 'nieuw@example.test')->sole();
    $userId = modelKeyString($user);

    expect($result->data['user_id'])->toBe($userId)
        ->and(DB::table('tenant_user')->where('user_id', $userId)->where('tenant_id', governanceTenantId())->value('role_id'))->toBe(modelKeyString(ownerRole()))
        ->and(storedInvitationEvent(invitationId($invited), 'accepted')['actor'])->toBe($userId)
        ->and(storedInvitationEvent(invitationId($invited), 'accepted')['org_id'])->toBe(governanceTenantId())
        ->and(storedInvitationEvent(invitationId($invited), 'accepted')['branch_id'])->toBe('main');

    $row = Invitation::query()->findOrFail(invitationId($invited));
    expect($row->accepted_at)->not->toBeNull()
        ->and($row->accepted_user_id)->toBe($userId);
});

it('refuses to accept a revoked, expired or used invitation', function (): void {
    $owner = invitationsManager();
    $accept = fn (string $token): ActionResult => app(AcceptInvitation::class)->handle($token, 'Iemand', 'secret-password');

    $revoked = invite($owner, 'ingetrokken@example.test');
    app(RevokeInvitation::class)->handle(invitationId($revoked), Actor::user($owner));
    expect($accept(inviteToken($revoked))->message)->toBe('Deze uitnodiging is ingetrokken.');

    $expired = invite($owner, 'verlopen@example.test');
    Carbon::setTestNow(now()->addDays(8));
    expect($accept(inviteToken($expired))->message)->toBe('Deze uitnodiging is verlopen.');
    Carbon::setTestNow();

    $used = invite($owner, 'gebruikt@example.test');
    expect($accept(inviteToken($used))->isDone())->toBeTrue()
        ->and($accept(inviteToken($used))->message)->toBe('Deze uitnodiging is al gebruikt.')
        ->and($accept('unknown')->isFailed())->toBeTrue()
        ->and(User::query()->whereIn('email', ['ingetrokken@example.test', 'verlopen@example.test'])->exists())->toBeFalse();
});

it('refuses to accept for an address that already has an account', function (): void {
    $owner = invitationsManager();
    $invited = invite($owner);
    User::factory()->create(['email' => 'nieuw@example.test']);

    expect(app(AcceptInvitation::class)->handle(inviteToken($invited), 'Iemand', 'secret-password')->isFailed())->toBeTrue()
        ->and(Invitation::query()->findOrFail(invitationId($invited))->accepted_at)->toBeNull();
});

it('rebuilds the invitations table from the events alone', function (): void {
    $owner = invitationsManager();
    $revoked = invite($owner, 'ingetrokken@example.test');
    app(RevokeInvitation::class)->handle(invitationId($revoked), Actor::user($owner));
    $accepted = invite($owner, 'geaccepteerd@example.test');
    app(AcceptInvitation::class)->handle(inviteToken($accepted), 'Iemand', 'secret-password');
    invite($owner, 'open@example.test');

    $before = DB::table('invitations')->orderBy('id')->get()->toArray();

    DB::table('invitations')->truncate();
    app(Projectionist::class)->replay(collect([app(InvitationProjector::class)]));

    expect($before)->toHaveCount(3)
        ->and(DB::table('invitations')->orderBy('id')->get()->toArray())->toEqual($before);
});

it('rejects an invitation event whose payload does not match its schema', function (): void {
    $event = (new ReflectionClass(InvitationCreated::class))->newInstanceWithoutConstructor();

    foreach (['invitation_id' => (string) Str::ulid(), 'org_id' => 'default', 'branch_id' => 'main', 'email' => 'x@example.test', 'role_id' => 'r', 'invited_by' => '1', 'token_hash' => 'h'] as $field => $value) {
        (new ReflectionProperty($event, $field))->setValue($event, $value);
    }

    expect(fn () => app(StoredEventRepository::class)->persist($event, $event->invitation_id))
        ->toThrow(InvalidEventPayloadException::class, 'expires_at');
    expect(DB::table('events')->where('event_type', 'invitations.invitation.created')->exists())->toBeFalse();
});

it('serves the framework invitation contract through InviteUser', function (): void {
    $owner = invitationsManager();
    $tenant = Tenant::query()->findOrFail(governanceTenantId());

    $invitation = app(InviteUserToTenant::class)->handle($tenant, $owner, 'nieuw@example.test', 'owner');

    expect($invitation)->toBeInstanceOf(Invitation::class)
        ->and($invitation->getAttribute('email'))->toBe('nieuw@example.test');
});
