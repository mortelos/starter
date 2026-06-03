<?php

declare(strict_types=1);

use App\Access\StarterGovernanceGate;
use App\Contracts\GovernanceGate;
use App\Models\Policy;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('binds the contract to the starter gate', function (): void {
    expect(app(GovernanceGate::class))->toBeInstanceOf(StarterGovernanceGate::class);
});

it('denies by default when a user holds no role', function (): void {
    $user = User::factory()->create();

    expect(app(GovernanceGate::class)->canManage($user))->toBeFalse();
});

it('denies when no user is given', function (): void {
    expect(app(GovernanceGate::class)->canManage(null))->toBeFalse();
});

it('allows when a role has an explicit allow policy for the action', function (): void {
    $user = User::factory()->create();
    $role = Role::factory()->create();
    Policy::factory()->for($role)->action('governance.manage')->allow()->create();
    $user->roles()->attach($role);

    expect(app(GovernanceGate::class)->canManage($user))->toBeTrue();
});

it('denies when the only matching policy has a deny effect', function (): void {
    $user = User::factory()->create();
    $role = Role::factory()->create();
    Policy::factory()->for($role)->action('governance.manage')->deny()->create();
    $user->roles()->attach($role);

    expect(app(GovernanceGate::class)->canManage($user))->toBeFalse();
});

it('denies when the allow policy is for a different action', function (): void {
    $user = User::factory()->create();
    $role = Role::factory()->create();
    Policy::factory()->for($role)->action('something.else')->allow()->create();
    $user->roles()->attach($role);

    expect(app(GovernanceGate::class)->canManage($user))->toBeFalse();
});

it('checks an arbitrary action through allows()', function (): void {
    $user = User::factory()->create();
    $role = Role::factory()->create();
    Policy::factory()->for($role)->action('users.manage')->allow()->create();
    $user->roles()->attach($role);

    $gate = app(StarterGovernanceGate::class);

    expect($gate->allows($user, 'users.manage'))->toBeTrue();
    expect($gate->allows($user, 'governance.manage'))->toBeFalse();
});
