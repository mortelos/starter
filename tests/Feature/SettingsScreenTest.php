<?php

declare(strict_types=1);

use App\Actions\Account\ChangePassword;
use App\Actions\Account\UpdateProfile;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('shows the profile confirmation after saving', function (): void {
    actingAs(User::factory()->create());

    Livewire::test('pages::settings.index')
        ->set('name', 'Nieuwe Naam')
        ->call('updateProfile')
        ->assertSee('Profiel bijgewerkt.')
        ->assertHasNoErrors();

    expect(User::query()->where('name', 'Nieuwe Naam')->exists())->toBeTrue();
});

it('saves the profile without writing back a password changed in the meantime', function (): void {
    $user = User::factory()->create();
    actingAs($user);

    // The password island may save while the profile island runs; this user instance is then stale.
    User::query()->whereKey($user->getKey())->update(['password' => 'hash-of-the-password-island']);

    Livewire::test('pages::settings.index')
        ->set('name', 'Nieuwe Naam')
        ->call('updateProfile');

    expect(User::query()->whereKey($user->getKey())->value('password'))->toBe('hash-of-the-password-island');
});

it('shows the wrong current password error', function (): void {
    actingAs(User::factory()->create());

    Livewire::test('pages::settings.index')
        ->set('current_password', 'not-the-password')
        ->set('password', 'a-new-password')
        ->set('password_confirmation', 'a-new-password')
        ->call('updatePassword')
        ->assertSee('Huidige wachtwoord klopt niet.')
        ->assertHasErrors(['current_password']);
});

it('refuses a profile change by anyone but the account owner', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    expect(fn () => app(UpdateProfile::class)->handle($owner, 'Nieuwe naam', 'nieuw@example.test', $other))
        ->toThrow(AuthorizationException::class);

    expect($owner->fresh()?->name)->not->toBe('Nieuwe naam');
});

it('enforces password defaults and confirmation in the action and screen', function (string $password, ?string $confirmation): void {
    Password::defaults(fn () => Password::min(12)->mixedCase()->numbers()->symbols());
    try {
        $user = User::factory()->create();
        $hash = $user->password;
        actingAs($user);
        expect(fn () => app(ChangePassword::class)->handle($user, 'password', $password, $user, $confirmation))
            ->toThrow(ValidationException::class);
        Livewire::test('pages::settings.index')
            ->set('current_password', 'password')
            ->set('password', $password)
            ->set('password_confirmation', $confirmation ?? '')
            ->call('updatePassword')->assertHasErrors('password');
        expect($user->fresh()?->password)->toBe($hash);
    } finally {
        Password::defaults(fn () => Password::min(8));
    }
})->with([['short', 'short'], ['Long-enough-123!', null], ['Long-enough-123!', 'different'], ['lowercase-password', 'lowercase-password']]);

it('changes a confirmed strong password directly', function (): void {
    $user = User::factory()->create();
    app(ChangePassword::class)->handle($user, 'password', 'Strong-password-123!', $user, 'Strong-password-123!');
    expect(Hash::check('Strong-password-123!', $user->fresh()->password ?? ''))->toBeTrue();
});

it('validates email in both entry points', function (string $email): void {
    $user = User::factory()->create();
    User::factory()->create(['email' => 'taken@example.test']);
    $original = $user->email;
    actingAs($user);
    expect(fn () => app(UpdateProfile::class)->handle($user, 'Name', $email, $user))
        ->toThrow(ValidationException::class);
    Livewire::test('pages::settings.index')->set('email', $email)->call('updateProfile')->assertHasErrors('email');
    expect($user->fresh()?->email)->toBe($original);
})->with(['', 'invalid', 'taken@example.test', 'TAKEN@example.test', str_repeat('a', 64).'@'.str_repeat('b', 63).'.'.str_repeat('c', 63).'.'.str_repeat('d', 63).'.test']);

it('normalizes email and permits the own address', function (): void {
    $user = User::factory()->create(['email' => 'own@example.test']);
    app(UpdateProfile::class)->handle($user, 'Name', 'OWN@EXAMPLE.TEST', $user);
    expect($user->fresh()?->email)->toBe('own@example.test');
    actingAs($user);
    $page = Livewire::test('pages::settings.index')->set('email', 'NEW@EXAMPLE.TEST')->call('updateProfile');
    $page->assertHasNoErrors();
    $page->assertSet('email', 'new@example.test');
    expect($user->fresh()?->email)->toBe('new@example.test');
});

it('runs the production leak check only once and displays its error', function (): void {
    $verifier = Mockery::mock(UncompromisedVerifier::class);
    $verifier->shouldReceive('verify')->once()->andReturn(false);
    app()->instance(UncompromisedVerifier::class, $verifier);
    Password::defaults(fn () => Password::min(12)->mixedCase()->numbers()->symbols()->uncompromised());
    try {
        $user = User::factory()->create();
        $hash = $user->password;
        actingAs($user);
        Livewire::test('pages::settings.index')
            ->set('current_password', 'password')
            ->set('password', 'Strong-password-123!')
            ->set('password_confirmation', 'Strong-password-123!')
            ->call('updatePassword')->assertHasErrors('password');
        expect($user->fresh()?->password)->toBe($hash);
    } finally {
        Password::defaults(fn () => Password::min(8));
    }
});

it('clears profile errors after correcting a duplicate email', function (): void {
    $user = User::factory()->create();
    User::factory()->create(['email' => 'taken@example.test']);
    actingAs($user);
    $page = Livewire::test('pages::settings.index')->set('email', 'taken@example.test')->call('updateProfile');
    $page->assertHasErrors('email');
    $page->set('email', 'available@example.test')->call('updateProfile');
    $page->assertHasNoErrors(['name', 'email']);
    $page->assertSee('Profiel bijgewerkt.');
});

it('enforces the explicit email storage limit', function (): void {
    $email = str_repeat('a', 64).'@'.str_repeat('b', 63).'.'.str_repeat('c', 63).'.'.str_repeat('d', 63).'.test';
    // Each local/domain part fits its limit; RFC validation also rejects the total length.
    // Assert Max explicitly so removing max:255 cannot be hidden by that other error.
    $user = User::factory()->create();
    $validator = Validator::make(['name' => 'Name', 'email' => $email], UpdateProfile::rules($user));
    $validator->passes();
    expect($validator->failed()['email'])->toHaveKey('Max');
});
