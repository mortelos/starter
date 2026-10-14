<?php

declare(strict_types=1);

use App\Actions\Invitations\InviteUser;
use App\Actions\Invitations\RevokeInvitation;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mortel\Models\Role;
use Mortel\Support\Actor;
use Mortel\Support\Scalar;

use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\from;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

/** Invites gast@example.test as owner and returns the plain token from the link. */
function acceptableInviteToken(): string
{
    $actor = Actor::user(invitationsManager());
    $result = app(InviteUser::class)->handle('gast@example.test', modelKeyString(Role::query()->where('name', 'owner')->firstOrFail()), $actor);

    return Str::afterLast(Scalar::string($result->data['accept_url']), '/invite/');
}

it('shows the form with the invited email address', function (): void {
    get(route('invite.show', ['token' => acceptableInviteToken()]))
        ->assertOk()
        ->assertSee('gast@example.test')
        ->assertSee('Account aanmaken');
});

it('answers 404 for an unknown token', function (): void {
    get(route('invite.show', ['token' => 'onbekend']))->assertNotFound();
});

it('explains a revoked invitation without a form', function (): void {
    $token = acceptableInviteToken();
    app(RevokeInvitation::class)->handle(Invitation::query()->sole()->id, Actor::user(User::query()->firstOrFail()));

    get(route('invite.show', ['token' => $token]))
        ->assertOk()
        ->assertSee('Deze uitnodiging werkt niet meer')
        ->assertDontSee('Account aanmaken');
});

it('creates the account with its membership and logs it in', function (): void {
    $token = acceptableInviteToken();

    post(route('invite.store', ['token' => $token]), [
        'name' => 'Gast',
        'password' => 'een-goed-wachtwoord',
        'password_confirmation' => 'een-goed-wachtwoord',
    ])->assertRedirect(route('dashboard'));

    $user = User::query()->where('email', 'gast@example.test')->sole();
    assertAuthenticatedAs($user);
    expect(DB::table('tenant_user')->where('user_id', modelKeyString($user))->where('tenant_id', governanceTenantId())->value('role_id'))
        ->toBe(Invitation::query()->sole()->role_id);
});

it('sends a revoked invitation back with the message and creates no user', function (): void {
    $token = acceptableInviteToken();
    app(RevokeInvitation::class)->handle(Invitation::query()->sole()->id, Actor::user(User::query()->firstOrFail()));
    $url = route('invite.show', ['token' => $token]);

    from($url)->post(route('invite.store', ['token' => $token]), [
        'name' => 'Gast',
        'password' => 'een-goed-wachtwoord',
        'password_confirmation' => 'een-goed-wachtwoord',
    ])->assertRedirect($url)->assertSessionHasErrors('email');

    assertGuest();
    expect(User::query()->where('email', 'gast@example.test')->exists())->toBeFalse();
});

it('rejects a password confirmation that does not match', function (): void {
    post(route('invite.store', ['token' => acceptableInviteToken()]), [
        'name' => 'Gast',
        'password' => 'een-goed-wachtwoord',
        'password_confirmation' => 'iets-anders',
    ])->assertSessionHasErrors('password');

    assertGuest();
    expect(User::query()->where('email', 'gast@example.test')->exists())->toBeFalse();
});
