<?php

declare(strict_types=1);

use App\Access\StarterGovernanceGate;
use App\Contracts\GovernanceGate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mortel\Enums\PolicyEffect;

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

it('denies a tenant membership without a role', function (): void {
    $user = User::factory()->create();
    attachGovernanceMembership($user, null);

    expect(app(GovernanceGate::class)->canManage($user))->toBeFalse();
});

it('allows when the membership role has an explicit allow policy for the ability', function (): void {
    $user = User::factory()->create();
    $role = createRole('reviewer');
    grantPolicy($role, 'governance.manage');
    attachGovernanceMembership($user, $role);

    expect(app(GovernanceGate::class)->canManage($user))->toBeTrue();
});

it('denies when the only matching policy has a deny effect', function (): void {
    $user = User::factory()->create();
    $role = createRole('reviewer');
    grantPolicy($role, 'governance.manage', PolicyEffect::Deny);
    attachGovernanceMembership($user, $role);

    expect(app(GovernanceGate::class)->canManage($user))->toBeFalse();
});

it('denies when the allow policy is for a different ability', function (): void {
    $user = User::factory()->create();
    $role = createRole('reviewer');
    grantPolicy($role, 'something.else');
    attachGovernanceMembership($user, $role);

    expect(app(GovernanceGate::class)->canManage($user))->toBeFalse();
});

it('checks an arbitrary ability through allows()', function (): void {
    $user = User::factory()->create();
    $role = createRole('reviewer');
    grantPolicy($role, 'users.manage');
    attachGovernanceMembership($user, $role);

    $gate = app(StarterGovernanceGate::class);

    expect($gate->allows($user, 'users.manage'))->toBeTrue();
    expect($gate->allows($user, 'governance.manage'))->toBeFalse();
});
