<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Livewire\Livewire;
use Mortel\SocialLogin\Contracts\DetectsSignInMethods;
use Mortel\SocialLogin\Contracts\ProvisionsSocialUsers;
use Mortel\SocialLogin\Events\SocialLoginSucceeded;
use Mortel\SocialLogin\Models\SocialIdentity;
use Mortel\SocialLogin\Provisioning\RefuseUnknownUsers;
use Mortel\SocialLogin\SocialLogin;
use Mortel\SocialLogin\Support\SignInMethods;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

const LINK_FIRST_MESSAGE = 'Log eerst in met je wachtwoord en koppel daarna Google op je profiel (Instellingen).';

function googleProfile(string $email, bool $verified = true, string $id = 'google-1001'): SocialiteUser
{
    $raw = ['sub' => $id, 'email' => $email, 'email_verified' => $verified, 'name' => 'Proefgebruiker'];

    return (new SocialiteUser)->setRaw($raw)->map(['id' => $id, 'name' => 'Proefgebruiker', 'email' => $email, 'avatar' => null]);
}

/** The user starts linking from the settings page, as the card does. */
function startLinking(User $user, string $provider = 'google'): void
{
    actingAs($user)->get(route('settings.social-login.link', ['provider' => $provider]));
    get('/login/'.$provider.'/link');
}

/** A refused link goes home with the reason, and home shows it on the settings page. */
function followRefusedLink(string $message): void
{
    get('/')->assertRedirect(route('settings'));
    get(route('settings'))->assertSee($message);
}

beforeEach(function (): void {
    config([
        'social-login.providers' => ['google', 'microsoft'],
        'social-login.google.client_id' => 'google-test-id',
        'social-login.google.client_secret' => 'google-test-secret',
        'social-login.microsoft.client_id' => null,
        'social-login.microsoft.client_secret' => null,
    ]);
});

it('shows a button only for a provider with client id and secret', function (): void {
    get('/login')
        ->assertOk()
        ->assertSee('data-social-login="google"', false)
        ->assertSee('Inloggen met Google')
        ->assertDontSee('data-social-login="microsoft"', false)
        ->assertSee('name="password"', false);
});

it('shows no button and no divider when no provider has its keys', function (): void {
    config(['social-login.google.client_secret' => null]);

    get('/login')
        ->assertOk()
        ->assertDontSee('data-social-login', false)
        ->assertDontSee('Inloggen met Google')
        ->assertSee('name="password"', false);
});

it('ships .env.example with the social login keys empty', function (): void {
    $env = (string) file_get_contents(base_path('.env.example'));

    foreach (['GOOGLE_CLIENT_ID', 'GOOGLE_CLIENT_SECRET', 'MICROSOFT_CLIENT_ID', 'MICROSOFT_CLIENT_SECRET', 'MICROSOFT_TRUSTED_TENANTS'] as $key) {
        expect($env)->toMatch('/^SOCIAL_LOGIN_'.$key.'=$/m');
    }
});

it('keeps the strict package policy: unknown people are refused and a password counts as a way to sign in', function (): void {
    expect(app(ProvisionsSocialUsers::class))->toBeInstanceOf(RefuseUnknownUsers::class)
        ->and(app(DetectsSignInMethods::class))->toBeInstanceOf(SignInMethods::class);
});

it('refuses an address that has no account, and creates none', function (): void {
    Socialite::fake(SocialLogin::driverName('google'), googleProfile('onbekend@example.test'));

    get('/login/google/callback?code=abc&state=xyz')
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['social_login' => 'Er is nog geen account voor onbekend@example.test. Vraag iemand van je team om je uit te nodigen.']);

    assertGuest();
    expect(User::query()->count())->toBe(0)->and(SocialIdentity::query()->count())->toBe(0);
});

it('refuses a first sign-in for someone who has a password and says to link first', function (): void {
    $user = User::factory()->create(['email' => 'beheerder@example.test']);
    Socialite::fake(SocialLogin::driverName('google'), googleProfile('beheerder@example.test'));

    get('/login/google/callback?code=abc&state=xyz')
        ->assertRedirect(route('login'));

    assertGuest();
    expect(SocialIdentity::query()->count())->toBe(0)
        ->and($user->fresh()?->password)->toBe($user->password);

    // The message is on the login page, once (reading the session errors in an assertion would consume them).
    $page = get('/login')->assertSee(LINK_FIRST_MESSAGE);
    expect(substr_count((string) $page->getContent(), LINK_FIRST_MESSAGE))->toBe(1);
});

it('links on the profile after a password login, and then the button signs in', function (): void {
    $user = User::factory()->create(['email' => 'beheerder@example.test']);
    Socialite::fake(SocialLogin::driverName('google'), googleProfile('beheerder@example.test'));

    startLinking($user);
    get('/login/google/callback?code=abc&state=xyz')->assertRedirect(route('settings'));

    $identity = SocialIdentity::query()->sole();
    expect($identity->user_id)->toEqual($user->getKey())->and($identity->provider)->toBe('google');

    auth()->logout();
    Event::fake([SocialLoginSucceeded::class]);

    get('/login/google/callback?code=abc&state=xyz')->assertRedirect(route('home'));

    assertAuthenticatedAs($user);
    Event::assertDispatched(SocialLoginSucceeded::class, 1);
});

it('refuses to link a provider account with another address', function (): void {
    $user = User::factory()->create(['email' => 'beheerder@example.test']);
    Socialite::fake(SocialLogin::driverName('google'), googleProfile('iemand-anders@example.test'));

    startLinking($user);
    get('/login/google/callback?code=abc&state=xyz')->assertRedirect('/');

    followRefusedLink('Log eerst in met je wachtwoord');

    expect(SocialIdentity::query()->count())->toBe(0);
});

it('refuses to link a provider address that the provider has not verified', function (): void {
    $user = User::factory()->create(['email' => 'beheerder@example.test']);
    Socialite::fake(SocialLogin::driverName('google'), googleProfile('beheerder@example.test', verified: false));

    startLinking($user);
    get('/login/google/callback?code=abc&state=xyz')->assertRedirect('/');

    followRefusedLink('Je Google-account heeft geen bevestigd e-mailadres dat we kunnen gebruiken. Neem contact op met de beheerder.');

    expect(SocialIdentity::query()->count())->toBe(0);
});

it('asks for the two-factor code after the provider, and signs in only after the code', function (): void {
    $user = userWithTwoFactor();
    SocialIdentity::query()->create(['user_id' => $user->getKey(), 'provider' => 'google', 'provider_user_id' => 'google-1001', 'email' => $user->email]);
    Socialite::fake(SocialLogin::driverName('google'), googleProfile($user->email));
    Event::fake([SocialLoginSucceeded::class]);

    get('/login/google/callback?code=abc&state=xyz')->assertRedirect(route('two-factor.login'));

    assertGuest();
    Event::assertNotDispatched(SocialLoginSucceeded::class);

    post(route('two-factor.login.store'), ['code' => twoFactorCode($user)])->assertRedirect(route('home'));

    assertAuthenticatedAs($user);
    Event::assertDispatched(SocialLoginSucceeded::class, 1);
});

it('accepts a recovery code after the provider too', function (): void {
    $user = userWithTwoFactor();
    SocialIdentity::query()->create(['user_id' => $user->getKey(), 'provider' => 'google', 'provider_user_id' => 'google-1001', 'email' => $user->email]);
    Socialite::fake(SocialLogin::driverName('google'), googleProfile($user->email));

    get('/login/google/callback?code=abc&state=xyz');
    post(route('two-factor.login.store'), ['recovery_code' => 'aaaaaaaaaa-1111111111'])->assertRedirect(route('home'));

    assertAuthenticatedAs($user);
});

it('shows the link card on the settings page with a button per provider that is set up', function (): void {
    $user = User::factory()->create();
    actingAs($user);

    Livewire::test('pages::settings.index')
        ->assertSee('Inloggen met Google of Microsoft')
        ->assertSee('Koppel Google')
        ->assertDontSee('Koppel Microsoft')
        ->assertSeeHtml('href="'.route('settings.social-login.link', ['provider' => 'google']).'"');

    SocialIdentity::query()->create(['user_id' => $user->getKey(), 'provider' => 'google', 'provider_user_id' => 'google-1001', 'email' => $user->email]);

    Livewire::test('pages::settings.index')
        ->assertSee('Gekoppeld')
        ->assertDontSee('Koppel Google');
});

it('leaves the link card off the settings page when no provider is set up', function (): void {
    config(['social-login.google.client_secret' => null]);
    actingAs(User::factory()->create());

    Livewire::test('pages::settings.index')->assertDontSee('Inloggen met Google of Microsoft');
});

it('lets the link route be used by a signed-in user only', function (): void {
    get('/login/google/link')->assertRedirect(route('login'));
});

it('starts linking from the settings page and comes back to it', function (): void {
    $user = User::factory()->create(['email' => 'beheerder@example.test']);
    Socialite::fake(SocialLogin::driverName('google'), googleProfile('beheerder@example.test'));

    actingAs($user)->get(route('settings.social-login.link', ['provider' => 'google']))
        ->assertRedirect(route('social-login.link', ['provider' => 'google']));
    get('/login/google/link')->assertRedirect();
    get('/login/google/callback?code=abc&state=xyz')->assertRedirect(route('settings'));

    expect(SocialIdentity::query()->count())->toBe(1);
});

it('shows a refused link on the settings page, not on the dashboard', function (): void {
    $user = User::factory()->create(['email' => 'beheerder@example.test']);
    Socialite::fake(SocialLogin::driverName('google'), googleProfile('iemand-anders@example.test'));

    actingAs($user)->get(route('settings.social-login.link', ['provider' => 'google']));
    get('/login/google/link');
    get('/login/google/callback?code=abc&state=xyz')->assertRedirect('/');

    get('/')->assertRedirect(route('settings'));
    get(route('settings'))->assertSee('Inloggen met Google of Microsoft');

    expect(SocialIdentity::query()->count())->toBe(0);
});

it('lets a visitor who was heading somewhere else go there after the provider', function (): void {
    $user = userWithTwoFactor();
    SocialIdentity::query()->create(['user_id' => $user->getKey(), 'provider' => 'google', 'provider_user_id' => 'google-1001', 'email' => $user->email]);
    Socialite::fake(SocialLogin::driverName('google'), googleProfile($user->email));

    get('/inbox')->assertRedirect(route('login'));
    get('/login/google/callback?code=abc&state=xyz')->assertRedirect(route('two-factor.login'));
    post(route('two-factor.login.store'), ['code' => twoFactorCode($user)])->assertRedirect(url('/inbox'));
});
