<?php

declare(strict_types=1);

use App\Contracts\GovernanceGate;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mortel\Events\Role\RoleCreated;
use Mortel\Models\Policy;
use Mortel\Models\Role;
use Mortel\Models\UteqStoredEvent;

use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

it('seeds an owner that can manage governance and users', function (): void {
    seed(DatabaseSeeder::class);

    $admin = User::query()->where('email', 'admin@example.test')->firstOrFail();
    $gate = app(GovernanceGate::class);

    expect($gate->canManage($admin))->toBeTrue();
    expect($gate->allows($admin, 'users.manage'))->toBeTrue();
});

it('records the owner role and its policies as stored events', function (): void {
    seed(DatabaseSeeder::class);

    $owner = Role::query()->where('name', 'owner')->firstOrFail();

    expect(UteqStoredEvent::where('event_class', RoleCreated::class)->count())->toBe(1);
    expect(UteqStoredEvent::where('aggregate_uuid', modelKeyString($owner))->count())->toBe(1);
    expect(roleHasPolicy($owner, 'governance.manage'))->toBeTrue();
    expect(roleHasPolicy($owner, 'users.manage'))->toBeTrue();
});

it('is idempotent when re-seeding does not duplicate the owner role or policies', function (): void {
    seed(DatabaseSeeder::class);
    seed(DatabaseSeeder::class);

    $owner = Role::query()->where('name', 'owner')->firstOrFail();

    expect(Role::query()->where('name', 'owner')->count())->toBe(1);
    expect(Policy::query()->where('role_id', modelKeyString($owner))->count())->toBe(2);
    expect(UteqStoredEvent::where('event_class', RoleCreated::class)->count())->toBe(1);
});
