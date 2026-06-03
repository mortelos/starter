<?php

declare(strict_types=1);

use App\Contracts\GovernanceGate;
use App\Models\Policy;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

it('seeds an owner that can manage governance and users', function (): void {
    seed(DatabaseSeeder::class);

    $admin = User::query()->where('email', 'admin@example.test')->firstOrFail();
    $gate = app(GovernanceGate::class);

    expect($gate->canManage($admin))->toBeTrue();
    expect($gate->allows($admin, 'users.manage'))->toBeTrue();
});

it('is idempotent when re-seeding does not duplicate the owner role or policies', function (): void {
    seed(DatabaseSeeder::class);
    seed(DatabaseSeeder::class);

    expect(Role::query()->where('name', 'Owner')->count())->toBe(1);
    expect(Policy::query()->where('action', 'governance.manage')->count())->toBe(1);
});
