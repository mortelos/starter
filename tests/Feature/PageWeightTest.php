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
 * The pages of this app: GET routes behind sign-in without a parameter. The routes of packages (Horizon, Livewire,
 * the kit's own `mortel.` routes) are theirs to test. A page you add shows up here by itself.
 *
 * @return list<string>
 */
function weightPages(): array
{
    $pages = collect(Router::getRoutes()->getRoutes())
        ->filter(fn (Route $route): bool => in_array('GET', $route->methods(), true)
            && in_array('auth', $route->gatherMiddleware(), true)
            && ! str_contains($route->uri(), '{')
            && ! str_starts_with((string) $route->getName(), 'mortel.'))
        ->map(fn (Route $route): string => '/'.ltrim($route->uri(), '/'))
        ->all();

    return array_values($pages);
}

// What it proves: one more open invitation on the users page costs at most 5 KB and 40 elements, and no query,
// no Alpine controller and no Livewire child. Copy it for a list of your own: the seed grows the list to n rows
// and returns their ids, `rows` selects the row element, and every row carries a `data-id`.
it('keeps the cost per row of the pending invitations list inside the budget', function (): void {
    PageWeight::perItem(
        seed: fn (int $n): array => (new ScaleSeeder)->invitations($n),
        url: '/users',
        rows: '[data-invite-row]',
        sizes: [5, 15],
        request: fn (string $url) => get($url),
        login: fn () => actingAs(weightAdmin()),
        fakeAi: fn () => null,
    );
})->group('page-weight');

it('keeps every page inside the page budget and grows no page per item', function (): void {
    PageWeight::sweep(
        routes: weightPages(),
        request: fn (string $url) => get($url),
        login: fn () => actingAs(weightAdmin()),
        fakeAi: fn () => null,
        exclude: ['/changelog/export' => 'Download of the change log, not a page.'],
    )->scaled(fn (int $n) => (new ScaleSeeder)->scale($n), [1, 3]);
})->group('page-weight');
