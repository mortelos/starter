<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ScaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use Mortelos\Ui\Testing\PageWeight;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\seed;

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
 * The GET routes the sweep leaves out, each with its reason: every route that is not a page of this app (no sign-in,
 * or a route of the kit or of a package such as Horizon) and the app pages that are no page to read. The sweep then
 * takes every other GET route by itself, so a page you add is in it without a line here.
 *
 * @return array<string, string>
 */
function weightExclusions(): array
{
    $excluded = [];
    foreach (Router::getRoutes()->getRoutes() as $route) {
        if (! in_array('GET', $route->methods(), true)) {
            continue;
        }
        $isAppPage = in_array('auth', $route->gatherMiddleware(), true) && ! str_starts_with((string) $route->getName(), 'mortel.');
        if (! $isAppPage) {
            $excluded['/'.ltrim($route->uri(), '/')] = 'Not a page of this app: a guest page (the sweep is signed in), an asset, the health check or a route of the kit or of a package. Its owner tests it.';
        }
    }

    return [
        ...$excluded,
        '/changelog/export' => 'Download of the change log, not a page.',
        '/settings/social-login/{provider}' => 'Starts linking a provider account: redirects to the provider.',
        '/login/{provider}/link' => 'Starts linking a provider account: redirects to the provider.',
    ];
}

/**
 * The paths of a route with parameters, for the sweep's `resolve:`. The sweep calls this while it runs, signed in as
 * the admin, so the seeding happens inside it. The starter has no page of its own with a parameter. When you add one,
 * seed what it needs and return its path, for example a record page:
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
        default => [],
    };
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

// What it proves: every GET page of the app answers 200 with its main content in the first response, inside the page
// budget, and no page asks more queries or grows per row when the data grows from 1 to 3 of each list (members and
// invitations). `notCovered` is asserted: a page without `data-mortel-main` fails the test and is either fixed or
// excluded with a reason above.
it('keeps every page inside the page budget and grows no page per item', function (): void {
    $weight = PageWeight::sweep(
        request: fn (string $url) => get($url),
        login: fn () => actingAs(weightAdmin()),
        fakeAi: fn () => null,
        exclude: weightExclusions(),
        resolve: fn (Route $route): array => weightResolve($route),
    );

    expect($weight->notCovered)->toBe([], 'Pages without a main region: '.json_encode($weight->notCoveredReasons));
    expect(array_keys($weight->measurements))->not->toBeEmpty();

    $weight->scaled(fn (int $n) => (new ScaleSeeder)->scale($n), [1, 3]);
})->group('page-weight');
