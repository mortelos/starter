<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mortel\Models\Entity;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

it('redirects guests to login', function (): void {
    get(route('entities.show', str_repeat('A', 26)))
        ->assertRedirect(route('login'));
});

it('returns 404 for a well-formed but unknown entity id', function (): void {
    actingAs(User::factory()->create());

    get(route('entities.show', str_repeat('A', 26)))
        ->assertNotFound();
});

it('returns 404 for a malformed entity id', function (): void {
    actingAs(User::factory()->create());

    get(route('entities.show', 'not-a-ulid'))
        ->assertNotFound();
});

// A non-viewable entity is deliberately indistinguishable from a missing one:
// the access gate aborts 404 rather than leaking existence. A happy-path render
// test depends on the host portal's actor/grant wiring and lives there.
it('returns 404 when the actor may not view the entity', function (): void {
    actingAs(User::factory()->create());

    $entity = Entity::factory()->create();

    get(route('entities.show', $entity->id))
        ->assertNotFound();
});
