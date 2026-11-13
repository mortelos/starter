<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Socialite\Contracts\Factory;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mortel\SocialLogin\Models\SocialIdentity;
use Mortel\SocialLogin\SocialLogin;
use Mortelos\Ui\Testing\LivewireMonitor;
use Pest\Browser\Api\PendingAwaitablePage;
use Tests\Support\LoopbackSocialite;

/*
 * Signing in with a second factor, as a person does it: the password, then the code from the authenticator app (or a
 * recovery code); Google or Microsoft, then the same code; and linking a provider on the settings page. The provider
 * is pretended (LoopbackSocialite), the codes are real TOTP codes of the user's stored secret.
 */

const CHALLENGE_CODE_FORM = 'form[action$="/two-factor-challenge"]:first-of-type';
const CHALLENGE_RECOVERY_FORM = 'form[action$="/two-factor-challenge"]:last-of-type';

function passwordStep(User $user): PendingAwaitablePage
{
    $page = LivewireMonitor::visit('/login');

    $page->fill('email', $user->email)
        ->fill('password', 'password')
        ->click('form[action$="/auth/login"] button[type="submit"]');

    return $page;
}

function pretendProvider(string $provider, string $email, string $id = 'provider-1001'): void
{
    config([
        'social-login.providers' => ['google', 'microsoft'],
        'social-login.google.client_id' => 'google-test-id',
        'social-login.google.client_secret' => 'google-test-secret',
    ]);

    $profile = (new SocialiteUser)
        ->setRaw(['sub' => $id, 'email' => $email, 'email_verified' => true, 'name' => 'Proefgebruiker'])
        ->map(['id' => $id, 'name' => 'Proefgebruiker', 'email' => $email, 'avatar' => null]);

    Socialite::swap(new LoopbackSocialite(app(Factory::class)));
    Socialite::fake(SocialLogin::driverName($provider), $profile);
}

it('logs in with the password and the code from the app', function (): void {
    $user = userWithTwoFactor();

    $page = passwordStep($user)
        ->assertPathIs('/two-factor-challenge')
        ->assertSee('Vul de code uit je authenticator-app in.');

    $page->fill('code', twoFactorCode($user))
        ->click(CHALLENGE_CODE_FORM.' button[type="submit"]')
        ->assertPathIs('/dashboard');
});

it('stays on the challenge after a wrong code', function (): void {
    $user = userWithTwoFactor();

    passwordStep($user)
        ->assertPathIs('/two-factor-challenge')
        ->fill('code', '000000')
        ->click(CHALLENGE_CODE_FORM.' button[type="submit"]')
        ->assertPathIs('/two-factor-challenge')
        ->assertSee('Deze code klopt niet.');
});

it('logs in with a recovery code', function (): void {
    $user = userWithTwoFactor();

    passwordStep($user)
        ->assertPathIs('/two-factor-challenge')
        ->click('Gebruik een herstelcode')
        ->assertSee('Vul een van je herstelcodes in.')
        ->fill('recovery_code', 'aaaaaaaaaa-1111111111')
        ->click(CHALLENGE_RECOVERY_FORM.' button[type="submit"]')
        ->assertPathIs('/dashboard');
});

it('signs in with Google and then asks for the code', function (): void {
    $user = userWithTwoFactor();
    SocialIdentity::query()->create(['user_id' => $user->getKey(), 'provider' => 'google', 'provider_user_id' => 'provider-1001', 'email' => $user->email]);
    pretendProvider('google', $user->email);

    $page = LivewireMonitor::visit('/login')
        ->assertSee('Inloggen met Google')
        ->click('Inloggen met Google')
        ->assertPathIs('/two-factor-challenge');

    $page->fill('code', twoFactorCode($user))
        ->click(CHALLENGE_CODE_FORM.' button[type="submit"]')
        ->assertPathIs('/dashboard');
});

it('links Google on the settings page and then signs in with it', function (): void {
    $user = User::factory()->create(['email' => 'koppelaar@example.test']);
    pretendProvider('google', 'koppelaar@example.test');

    $page = passwordStep($user)->assertPathIs('/dashboard')->navigate('/settings');

    $page->assertSee('Inloggen met Google of Microsoft')
        ->assertDontSee('Koppel Microsoft')
        ->click('Koppel Google')
        ->assertPathIs('/settings')
        ->assertSee('Gekoppeld');

    expect(SocialIdentity::query()->where('user_id', $user->getKey())->where('provider', 'google')->exists())->toBeTrue();

    // The logout button sits in the user menu; submit its form like the button does.
    $page->script("document.querySelector('form[action\$=\"/logout\"]').submit()");
    $page->assertPathIs('/login');
    $page->click('Inloggen met Google')->assertPathIs('/dashboard');
});

it('sets up two-factor on the settings page and then asks for the code at login', function (): void {
    $user = User::factory()->create(['email' => 'instellen@example.test']);

    $page = passwordStep($user)->assertPathIs('/dashboard')->navigate('/settings');

    $page->assertSee('Tweestaps instellen')
        ->fill('confirm_password', 'password')
        ->click('Tweestaps instellen')
        ->assertSee('Code uit de app')
        ->assertPresent('[data-test="two-factor-qr"] svg');

    $page->fill('code', twoFactorCode($user->fresh() ?? $user))
        ->click('Bevestigen')
        ->assertSee('Tweestaps staat aan.')
        ->assertPresent('[data-test="recovery-codes"]');

    expect($user->fresh()?->hasEnabledTwoFactorAuthentication())->toBeTrue();
});
