<?php

declare(strict_types=1);

use App\Actions\Invitations\InviteUser;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ScaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use Mortel\Models\Role;
use Mortel\Support\Actor;
use Mortel\Support\Scalar;
use Mortelos\Ui\Testing\PageWeight;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\seed;
use function Pest\Laravel\withSession;

/*
 * The server weight of a page, measured on the HTML a request returns (no browser). Two tests:
 *
 * - perItem(): what ONE more row of a list costs. The limits are fixed (5 KB, 40 elements, no Alpine controller,
 *   no Livewire child and no query per row), so a dropdown, a modal or a query inside a @foreach turns it red.
 * - sweep(): every GET page, for its size, its queries and a main content that is in the first response. scaled()
 *   then grows the data with ScaleSeeder and fails when a page asks more queries or grows per item.
 *
 * Both fake HTTP, mail and the queue. The starter calls no AI model, so the AI fake is a no-op; an app that calls
 * one installs its scripted fake there and never a real provider.
 */

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seed(DatabaseSeeder::class);
});

/** The seeded admin, who holds every right of the owner role. */
function weightAdmin(): User
{
    return User::query()->where('email', 'admin@example.test')->firstOrFail();
}

/**
 * The package that owns a route ("vendor/name"), or null when this app owns it. A route is a package's when its
 * controller class or its closure lives under vendor/. Two kinds are always the app's to measure: a Livewire page
 * (the user reads a page, whoever draws its component) and the routes in `$appRoutes`, which the app registers itself
 * with a controller of a package.
 */
function weightPackage(Route $route): ?string
{
    // Fortify's own routes are off; `routes/starter.php` registers this one page with Fortify's controller.
    $appRoutes = ['two-factor.login'];
    if ($route->getAction('livewire_component') !== null || in_array($route->getName(), $appRoutes, true)) {
        return null;
    }
    $uses = $route->getAction('uses');
    $controller = $route->getControllerClass();
    $file = match (true) {
        $uses instanceof Closure => (new ReflectionFunction($uses))->getFileName(),
        $controller !== null && class_exists($controller) => (new ReflectionClass($controller))->getFileName(),
        default => null,
    };
    $vendor = (string) realpath(base_path('vendor')).'/';
    if (! is_string($file) || ! str_starts_with($file, $vendor)) {
        return null;
    }
    [$organisation, $name] = explode('/', substr($file, strlen($vendor)));

    return $organisation.'/'.$name;
}

/**
 * The GET routes a sweep leaves out, each with its reason. A route of a package gets a generated reason with the
 * package name. A route of this app goes to exactly one of the two sweeps: the sweep that is signed in (routes behind
 * `auth`) or the guest sweep (the rest), and the other sweep names it as measured elsewhere. A route the app adds
 * is in a sweep by itself; when a page cannot be measured it needs its own true reason below, or the test fails.
 *
 * @return array<string, string>
 */
function weightExclusions(bool $signedIn): array
{
    $excluded = [];
    foreach (Router::getRoutes()->getRoutes() as $route) {
        if (! in_array('GET', $route->methods(), true)) {
            continue;
        }
        $uri = '/'.ltrim($route->uri(), '/');
        $package = weightPackage($route);
        $behindAuth = array_any($route->gatherMiddleware(), fn (mixed $middleware): bool => is_string($middleware) && ($middleware === 'auth' || str_starts_with($middleware, 'auth:')));
        if ($package !== null) {
            $excluded[$uri] = "Route of the package {$package}, not of this app.";
        } elseif ($behindAuth !== $signedIn) {
            $excluded[$uri] = $signedIn ? 'Guest page of this app: the guest sweep measures it.' : 'Page behind sign-in: the signed-in sweep measures it.';
        }
    }

    return [
        ...$excluded,
        '/' => 'Redirects to the login page or the start page.',
        '/changelog/export' => 'Download of the change log, not a page.',
        '/settings/social-login/{provider}' => 'Starts linking a provider account: redirects to the provider.',
    ];
}

/**
 * The paths of a route with parameters, for the sweep's `resolve:`. The sweep calls this while it runs, so the
 * seeding happens inside it. When you add a page with a parameter, seed what it needs and return its path, for
 * example a record page:
 *
 *     'records.show' => ['/records/'.(new ScaleSeeder)->record()],
 *
 * A route that returns [] is reported by the sweep as not covered, and the test fails until you resolve it or
 * exclude it with a reason above.
 *
 * @return list<string>
 */
function weightResolve(Route $route): array
{
    return match ($route->getName()) {
        'invite.show' => ['/invite/'.weightInviteToken()],
        default => [],
    };
}

/** The token of one open invitation, which only the accept address holds. */
function weightInviteToken(): string
{
    $admin = weightAdmin();
    $result = app(InviteUser::class)->handle(
        'page-weight@scale.example.test',
        Scalar::string(Role::query()->where('name', 'owner')->firstOrFail()->getKey()),
        Actor::user($admin),
    );
    expect($result->isFailed())->toBeFalse($result->message);

    return basename((string) parse_url(Scalar::string($result->data['accept_url'] ?? null), PHP_URL_PATH));
}

// What it proves: one more row on the users page costs at most 5 KB and 40 elements, and no query, no Alpine
// controller and no Livewire child. Copy it for a list of your own: the seed grows the list to n rows and returns the
// ids of its rows, `rows` selects the row element, and every row carries a `data-id`. One test per list: the members
// and the open invitations are two lists, so each has its own. The admin who signs in is a member too; the selector
// `[data-member-role=member]` counts only the members the seeder made (role "member"), and the invitation rows have
// their own marker.
it('keeps the cost per row of the members list inside the budget', function (): void {
    PageWeight::perItem(
        seed: fn (int $n): array => (new ScaleSeeder)->members($n),
        url: '/users', rows: '[data-member-role=member]', sizes: [5, 15],
        request: fn (string $url) => get($url),
        login: fn () => actingAs(weightAdmin()),
        fakeAi: fn () => null,
    );
})->group('page-weight');

it('keeps the cost per row of the pending invitations list inside the budget', function (): void {
    PageWeight::perItem(
        seed: fn (int $n): array => (new ScaleSeeder)->invitations($n),
        url: '/users', rows: '[data-invite-row]', sizes: [5, 15],
        request: fn (string $url) => get($url),
        login: fn () => actingAs(weightAdmin()),
        fakeAi: fn () => null,
    );
})->group('page-weight');

// What it proves: every GET page behind sign-in answers 200 with its main content in the first response, inside the
// page budget, and no page asks more queries or grows per row when the data grows from 1 to 3 of each list (members
// and invitations). `notCovered` is asserted: a page without `data-mortel-main` fails the test and is either fixed or
// excluded with a reason above.
it('keeps every page inside the page budget and grows no page per item', function (): void {
    $weight = PageWeight::sweep(
        request: fn (string $url) => get($url),
        login: fn () => actingAs(weightAdmin()),
        fakeAi: fn () => null,
        exclude: weightExclusions(signedIn: true),
        resolve: fn (Route $route): array => weightResolve($route),
    );

    expect($weight->notCovered)->toBe([], 'Pages without a main region: '.json_encode($weight->notCoveredReasons));
    expect(array_keys($weight->measurements))->not->toBeEmpty();

    $weight->scaled(fn (int $n) => (new ScaleSeeder)->scale($n), [1, 3]);
})->group('page-weight');

// What it proves: the guest pages of the app (the login page, the invitation page and the two-factor page) are inside
// the page budget too. It runs without a login; the invitation page needs a token, which the resolver seeds while the
// sweep runs, and the two-factor page needs the half-signed-in session that password login leaves.
it('keeps every guest page inside the page budget', function (): void {
    $weight = PageWeight::sweep(
        request: fn (string $url) => withSession(['login.id' => weightAdmin()->getKey(), 'login.remember' => false])->get($url),
        fakeAi: fn () => null,
        exclude: weightExclusions(signedIn: false),
        resolve: fn (Route $route): array => weightResolve($route),
    );

    expect($weight->notCovered)->toBe([], 'Pages without a main region: '.json_encode($weight->notCoveredReasons));
    expect(array_keys($weight->measurements))->not->toBeEmpty();
})->group('page-weight');
