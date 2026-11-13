<?php

declare(strict_types=1);

use App\Http\Middleware\SerializeTwoFactorChallenge;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;

use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\from;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

/** @return TestResponse<Response> */
function postPassword(User $user, string $password = 'password'): TestResponse
{
    return from('/login')->post('/auth/login', ['email' => $user->email, 'password' => $password]);
}

it('logs a user without two-factor straight in', function (): void {
    $user = User::factory()->create();

    postPassword($user)->assertRedirect(route('home'));

    assertAuthenticatedAs($user);
});

it('does not log in after the password when two-factor is on, but asks for the code', function (): void {
    Event::fake([Login::class]);
    $user = userWithTwoFactor();

    postPassword($user)->assertRedirect(route('two-factor.login'));

    assertGuest();
    Event::assertNotDispatched(Login::class);
    expect(session('login.id'))->toBe($user->getKey());
    get('/dashboard')->assertRedirect(route('login'));
});

it('keeps asking for the password before anything else: a wrong password never reaches the code', function (): void {
    $user = userWithTwoFactor();

    postPassword($user, 'not-the-password')->assertSessionHasErrors('email');

    expect(session('login.id'))->toBeNull();
    get(route('two-factor.login'))->assertRedirect(route('login'));
});

it('treats a setup that was never confirmed as no two-factor', function (): void {
    $user = userWithTwoFactor(confirmed: false);

    postPassword($user)->assertRedirect(route('home'));

    assertAuthenticatedAs($user);
});

it('shows the challenge page in Dutch with a field for the code and one for a recovery code', function (): void {
    $user = userWithTwoFactor();
    postPassword($user);

    get(route('two-factor.login'))
        ->assertOk()
        ->assertSee('name="code"', false)
        ->assertSee('name="recovery_code"', false)
        ->assertSee('Gebruik een herstelcode');
});

it('logs in with the code from the app and lands on the dashboard', function (): void {
    Event::fake([Login::class]);
    $user = userWithTwoFactor();
    postPassword($user);

    post(route('two-factor.login.store'), ['code' => twoFactorCode($user)])
        ->assertRedirect(route('home'));

    assertAuthenticatedAs($user);
    Event::assertDispatched(Login::class, 1);
});

it('refuses a wrong code and stays on the challenge', function (): void {
    $user = userWithTwoFactor();
    postPassword($user);

    post(route('two-factor.login.store'), ['code' => '000000'])
        ->assertRedirect(route('two-factor.login'))
        ->assertSessionHasErrors(['code' => 'Deze code klopt niet. Controleer de code in je app en probeer opnieuw.']);

    assertGuest();
});

it('logs in with a recovery code, and that code works only once', function (): void {
    $user = userWithTwoFactor();
    postPassword($user);

    post(route('two-factor.login.store'), ['recovery_code' => 'bbbbbbbbbb-2222222222'])->assertRedirect(route('home'));

    assertAuthenticatedAs($user);
    expect($user->fresh()?->recoveryCodes())->not->toContain('bbbbbbbbbb-2222222222')->toHaveCount(3);

    auth()->logout();
    postPassword($user);

    post(route('two-factor.login.store'), ['recovery_code' => 'bbbbbbbbbb-2222222222'])
        ->assertSessionHasErrors(['recovery_code' => 'Deze herstelcode klopt niet of is al gebruikt.']);

    assertGuest();
});

it('limits the guesses at the code with the limiter two-factor', function (): void {
    RateLimiter::clear('two-factor');
    $user = userWithTwoFactor();
    postPassword($user);

    foreach (range(1, 5) as $_) {
        post(route('two-factor.login.store'), ['code' => '000000'])->assertRedirect(route('two-factor.login'));
    }

    post(route('two-factor.login.store'), ['code' => twoFactorCode($user)])->assertStatus(429);

    assertGuest();
});

it('has the limiter two-factor and only Fortify routes the starter uses', function (): void {
    expect(RateLimiter::limiter('two-factor'))->not->toBeNull();

    $fortifyRoutes = collect(app('router')->getRoutes()->getRoutes())
        ->filter(fn ($route): bool => str_starts_with((string) $route->getActionName(), 'Laravel\\Fortify\\'))
        ->map(fn ($route): string => (string) $route->getName())
        ->sort()
        ->values()
        ->all();

    expect($fortifyRoutes)->toBe(['two-factor.login', 'two-factor.login.store']);
});

it('sends someone without a waiting login away from the challenge', function (): void {
    get(route('two-factor.login'))->assertRedirect(route('login'));
});

it('keeps the guards the same, which social login needs for the challenge', function (): void {
    expect(config('fortify.guard'))->toBe(config('auth.defaults.guard'));
});

it('remembers the user after the code when they asked for it', function (): void {
    $user = userWithTwoFactor();
    from('/login')->post('/auth/login', ['email' => $user->email, 'password' => 'password', 'remember' => '1']);

    expect(session('login.remember'))->toBeTrue();

    post(route('two-factor.login.store'), ['code' => twoFactorCode($user)])->assertRedirect(route('home'));

    expect($user->fresh()?->remember_token)->not->toBeNull();
});

it('does not expose the secret or the recovery codes when a user is serialised', function (): void {
    $array = userWithTwoFactor()->toArray();

    expect($array)->not->toHaveKeys(['two_factor_secret', 'two_factor_recovery_codes', 'password']);
});

it('checks one code at a time per waiting user: a second check meets the lock of the first', function (): void {
    config(['starter.auth.two_factor_lock_wait' => 0]);
    $user = userWithTwoFactor();
    postPassword($user);

    // Another request for this user is in the middle of its check (Fortify reads the codes, then saves the replacement).
    $running = Cache::lock(SerializeTwoFactorChallenge::key(modelKeyString($user)), 30);
    expect($running->get())->toBeTrue();

    post(route('two-factor.login.store'), ['recovery_code' => 'bbbbbbbbbb-2222222222'])->assertStatus(429);

    assertGuest();
    expect($user->fresh()?->recoveryCodes())->toBe(twoFactorRecoveryCodes());

    // The first request is done: the check goes on.
    $running->release();

    post(route('two-factor.login.store'), ['recovery_code' => 'bbbbbbbbbb-2222222222'])->assertRedirect(route('home'));

    assertAuthenticatedAs($user);
});

it('refuses a recovery code a second time, whether the second check waited for the first or came after it', function (): void {
    config(['starter.auth.two_factor_lock_wait' => 0]);
    $user = userWithTwoFactor();
    $key = SerializeTwoFactorChallenge::key(modelKeyString($user));

    // Check 1 is running; check 2 (same code) is turned away without being looked at.
    postPassword($user);
    $running = Cache::lock($key, 30);
    $running->get();
    post(route('two-factor.login.store'), ['recovery_code' => 'aaaaaaaaaa-1111111111'])->assertStatus(429);
    $running->release();

    // Check 1 finishes and replaces the code.
    post(route('two-factor.login.store'), ['recovery_code' => 'aaaaaaaaaa-1111111111'])->assertRedirect(route('home'));
    auth()->logout();

    // Check 2 comes again: the code is gone.
    postPassword($user);
    post(route('two-factor.login.store'), ['recovery_code' => 'aaaaaaaaaa-1111111111'])
        ->assertSessionHasErrors(['recovery_code']);

    assertGuest();
});

it('lets the check of another waiting user go on while one is locked', function (): void {
    config(['starter.auth.two_factor_lock_wait' => 0]);
    $locked = userWithTwoFactor();
    $other = userWithTwoFactor();
    postPassword($other);

    $running = Cache::lock(SerializeTwoFactorChallenge::key(modelKeyString($locked)), 30);
    $running->get();

    post(route('two-factor.login.store'), ['code' => twoFactorCode($other)])->assertRedirect(route('home'));

    assertAuthenticatedAs($other);
    $running->release();
});

it('frees the lock after a check, a failed one too', function (): void {
    $user = userWithTwoFactor();
    postPassword($user);

    post(route('two-factor.login.store'), ['code' => '000000'])->assertRedirect(route('two-factor.login'));

    $lock = Cache::lock(SerializeTwoFactorChallenge::key(modelKeyString($user)), 5);
    expect($lock->get())->toBeTrue();
    $lock->release();
});
