<?php

declare(strict_types=1);

use App\Contracts\GovernanceGate;
use App\Models\Policy;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds an owner that can manage governance and users', function (): void {
    $this->seed(DatabaseSeeder::class);

    $admin = User::query()->where('email', 'admin@example.test')->firstOrFail();
    $gate = app(GovernanceGate::class);

    expect($gate->canManage($admin))->toBeTrue();
    expect($gate->allows($admin, 'users.manage'))->toBeTrue();
});

it('is idempotent — re-seeding does not duplicate the owner role or policies', function (): void {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    expect(Role::query()->where('name', 'Owner')->count())->toBe(1);
    expect(Policy::query()->where('action', 'governance.manage')->count())->toBe(1);
});
