<?php

declare(strict_types=1);

use App\Actions\Account\ConfirmTwoFactor;
use App\Actions\Account\DisableTwoFactor;
use App\Actions\Account\EnableTwoFactor;
use App\Actions\Account\RegenerateRecoveryCodes;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('shows the card with the setup form when two-factor is off', function (): void {
    actingAs(User::factory()->create());

    Livewire::test('pages::settings.index')
        ->assertSee('Tweestaps')
        ->assertSee('Tweestaps instellen');
});

it('needs the password to start the setup', function (): void {
    $user = User::factory()->create();
    actingAs($user);

    Livewire::test('settings.two-factor')
        ->set('confirm_password', 'not-the-password')
        ->call('enable')
        ->assertHasErrors(['confirm_password']);

    expect($user->fresh()?->two_factor_secret)->toBeNull();
});

it('starts the setup with a QR code and the key, which does not count until the first code', function (): void {
    $user = User::factory()->create();
    actingAs($user);

    Livewire::test('settings.two-factor')
        ->set('confirm_password', 'password')
        ->call('enable')
        ->assertSeeHtml('data-test="two-factor-qr"')
        ->assertSeeHtml('data-test="two-factor-secret"')
        ->assertHasNoErrors();

    $fresh = $user->fresh();
    expect($fresh?->two_factor_secret)->not->toBeNull()
        ->and($fresh?->two_factor_confirmed_at)->toBeNull()
        ->and($fresh?->hasEnabledTwoFactorAuthentication())->toBeFalse();
});

it('keeps the secret and the recovery codes encrypted, not readable in the database', function (): void {
    $user = User::factory()->create();
    app(EnableTwoFactor::class)->handle($user, 'password', $user);

    $secret = DB::table('users')->where('id', $user->getKey())->value('two_factor_secret');
    $storedCodes = DB::table('users')->where('id', $user->getKey())->value('two_factor_recovery_codes');
    $codes = $user->fresh()?->recoveryCodes() ?? [];

    expect($secret)->not->toMatch('/^[A-Z2-7]{16}$/')
        ->and($storedCodes)->not->toContain($codes[0] ?? 'x')
        ->and($codes)->toHaveCount(8);
});

it('refuses a wrong first code and confirms with the right one', function (): void {
    $user = User::factory()->create();
    actingAs($user);

    $component = Livewire::test('settings.two-factor')
        ->set('confirm_password', 'password')
        ->call('enable')
        ->set('code', '000000')
        ->call('confirm');

    $component->assertHasErrors(['code']);

    expect($user->fresh()?->hasEnabledTwoFactorAuthentication())->toBeFalse();

    $component->set('code', twoFactorCode($user->fresh() ?? $user))
        ->call('confirm')
        ->assertSee('Tweestaps staat aan.')
        ->assertSeeHtml('data-test="recovery-codes"')
        ->assertHasNoErrors();

    expect($user->fresh()?->hasEnabledTwoFactorAuthentication())->toBeTrue();
});

it('cancels an unconfirmed setup without the password', function (): void {
    $user = User::factory()->create();
    actingAs($user);

    Livewire::test('settings.two-factor')
        ->set('confirm_password', 'password')
        ->call('enable')
        ->call('cancel')
        ->assertSee('Tweestaps instellen');

    expect($user->fresh()?->two_factor_secret)->toBeNull();
});

it('hides the recovery codes until the password is given, then shows them', function (): void {
    $user = userWithTwoFactor();
    actingAs($user);

    $component = Livewire::test('settings.two-factor')
        ->assertDontSeeHtml('data-test="recovery-codes"')
        ->set('confirm_password', 'not-the-password')
        ->call('reveal');

    $component->assertDontSeeHtml('data-test="recovery-codes"')->assertHasErrors(['confirm_password']);

    $component->set('confirm_password', 'password')
        ->call('reveal')
        ->assertSee('bbbbbbbbbb-2222222222')
        ->assertHasNoErrors();
});

it('replaces the recovery codes with the password as proof', function (): void {
    $user = userWithTwoFactor();
    actingAs($user);

    Livewire::test('settings.two-factor')
        ->set('confirm_password', 'not-the-password')
        ->call('regenerate')
        ->assertHasErrors(['confirm_password']);

    expect($user->fresh()?->recoveryCodes())->toBe(twoFactorRecoveryCodes());

    Livewire::test('settings.two-factor')
        ->set('confirm_password', 'password')
        ->call('regenerate')
        ->assertSee('Je hebt nieuwe herstelcodes.')
        ->assertHasNoErrors();

    expect($user->fresh()?->recoveryCodes())->not->toBe(twoFactorRecoveryCodes())->toHaveCount(8);
});

it('turns two-factor off only with the password and removes the secret', function (): void {
    $user = userWithTwoFactor();
    actingAs($user);

    Livewire::test('settings.two-factor')
        ->set('confirm_password', 'not-the-password')
        ->call('disable')
        ->assertHasErrors(['confirm_password']);

    expect($user->fresh()?->hasEnabledTwoFactorAuthentication())->toBeTrue();

    Livewire::test('settings.two-factor')
        ->set('confirm_password', 'password')
        ->call('disable')
        ->assertSee('Tweestaps staat uit.')
        ->assertHasNoErrors();

    $fresh = $user->fresh();
    expect($fresh?->hasEnabledTwoFactorAuthentication())->toBeFalse()
        ->and($fresh?->two_factor_secret)->toBeNull()
        ->and($fresh?->two_factor_recovery_codes)->toBeNull()
        ->and($fresh?->two_factor_confirmed_at)->toBeNull();
});

it('lets only the owner of an account change its two-factor setup', function (): void {
    $owner = userWithTwoFactor();
    $other = User::factory()->create();

    expect(fn () => app(EnableTwoFactor::class)->handle($owner, 'password', $other))->toThrow(AuthorizationException::class)
        ->and(fn () => app(ConfirmTwoFactor::class)->handle($owner, twoFactorCode($owner), $other))->toThrow(AuthorizationException::class)
        ->and(fn () => app(DisableTwoFactor::class)->handle($owner, 'password', $other))->toThrow(AuthorizationException::class)
        ->and(fn () => app(RegenerateRecoveryCodes::class)->handle($owner, 'password', $other))->toThrow(AuthorizationException::class);

    expect($owner->fresh()?->hasEnabledTwoFactorAuthentication())->toBeTrue();
});

it('keeps the secret when the setup is started again', function (): void {
    $user = User::factory()->create();
    app(EnableTwoFactor::class)->handle($user, 'password', $user);
    $secret = $user->fresh()?->two_factor_secret;

    app(EnableTwoFactor::class)->handle($user->fresh() ?? $user, 'password', $user);

    expect($user->fresh()?->two_factor_secret)->toBe($secret);
});

it('uses the same columns as Laravel Fortify', function (): void {
    expect(Schema::hasColumns('users', ['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at']))->toBeTrue();
});
