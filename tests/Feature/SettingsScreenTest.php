<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
    User::query()->whereKey($user->getKey())->update(['password' => 'hash-van-het-wachtwoord-island']);

    Livewire::test('pages::settings.index')
        ->set('name', 'Nieuwe Naam')
        ->call('updateProfile');

    expect(User::query()->whereKey($user->getKey())->value('password'))->toBe('hash-van-het-wachtwoord-island');
});

it('shows the wrong current password error', function (): void {
    actingAs(User::factory()->create());

    Livewire::test('pages::settings.index')
        ->set('current_password', 'niet-het-wachtwoord')
        ->set('password', 'een-nieuw-wachtwoord')
        ->set('password_confirmation', 'een-nieuw-wachtwoord')
        ->call('updatePassword')
        ->assertSee('Huidige wachtwoord klopt niet.')
        ->assertHasErrors(['current_password']);
});

it('refuses a profile change by anyone but the account owner', function (): void {
    $owner = \App\Models\User::factory()->create();
    $other = \App\Models\User::factory()->create();

    expect(fn () => app(\App\Actions\Account\UpdateProfile::class)->handle($owner, 'Nieuwe naam', 'nieuw@example.test', $other))
        ->toThrow(\Illuminate\Auth\Access\AuthorizationException::class);

    expect($owner->fresh()?->name)->not->toBe('Nieuwe naam');
});
