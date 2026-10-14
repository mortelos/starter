<?php

declare(strict_types=1);

namespace App\Actions\Invitations;

use App\Aggregates\InvitationAggregate;
use App\Exceptions\InvitationNotOpenException;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Mortel\Actions\ActionResult;
use Mortel\Support\Actor;
use Mortel\Support\EventContext;

/**
 * Turns an invitation into an account with a membership of the tenant. The
 * account, the membership and the event are one transaction. No Undo: there
 * is an account now, and taking a membership away is a governance decision
 * on the users screen, not the reverse of accepting.
 */
final class AcceptInvitation
{
    /** None: the visitor is not a user yet; holding the token is the authorization. */
    public const ?string ABILITY = null;

    public function handle(string $token, string $name, string $password): ActionResult
    {
        $invitation = Invitation::query()->where('token_hash', hash('sha256', $token))->first();

        if ($invitation === null) {
            return ActionResult::failed(InvitationNotOpenException::unknown()->getMessage());
        }

        $aggregate = InvitationAggregate::retrieve($invitation->id);
        $acceptedAt = now()->toIso8601String();

        try {
            $aggregate->ensureOpen($acceptedAt);

            if (User::query()->whereRaw('lower(email) = ?', [$invitation->email])->exists()) {
                return ActionResult::failed('Er bestaat al een account met '.$invitation->email.'. Log daarmee in en vraag de beheerder om je toe te voegen.');
            }

            $user = DB::transaction(function () use ($invitation, $aggregate, $acceptedAt, $name, $password): User {
                $user = User::query()->create([
                    'name' => $name,
                    'email' => $invitation->email,
                    'password' => Hash::make($password),
                ]);

                $userId = Actor::user($user)->id();

                DB::table('tenant_user')->insert([
                    'tenant_id' => $invitation->tenant_id,
                    'user_id' => $userId,
                    'role' => 'member',
                    'role_id' => $invitation->role_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                app(EventContext::class)->within(
                    Actor::user($user),
                    'Uitnodiging geaccepteerd',
                    fn () => $aggregate->accept($userId, $acceptedAt)->persist(),
                    $invitation->tenant_id,
                );

                return $user;
            });
        } catch (InvitationNotOpenException $e) {
            return ActionResult::failed($e->getMessage());
        }

        return ActionResult::done('Welkom, '.$name.'.', ['user_id' => Actor::user($user)->id()]);
    }
}
