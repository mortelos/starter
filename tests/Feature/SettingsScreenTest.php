<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('shows the profile confirmation after saving', function (): void {
    actingAs(User::factory()->create());

    Livewire::test('pages.settings.settings')
        ->set('name', 'Nieuwe Naam')
        ->call('updateProfile')
        ->assertSee('Profiel bijgewerkt.')
        ->assertHasNoErrors();

    expect(User::query()->where('name', 'Nieuwe Naam')->exists())->toBeTrue();
});

it('shows the wrong current password error', function (): void {
    actingAs(User::factory()->create());

    Livewire::test('pages.settings.settings')
        ->set('current_password', 'niet-het-wachtwoord')
        ->set('password', 'een-nieuw-wachtwoord')
        ->set('password_confirmation', 'een-nieuw-wachtwoord')
        ->call('updatePassword')
        ->assertSee('Huidige wachtwoord klopt niet.')
        ->assertHasErrors(['current_password']);
});
