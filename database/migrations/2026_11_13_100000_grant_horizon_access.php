<?php

declare(strict_types=1);

use App\Access\StarterGovernanceGate;
use App\Support\HorizonAccess;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Mortel\Actions\Policy\CreatePolicy;
use Mortel\Enums\PolicyEffect;
use Mortel\Models\Policy;
use Mortel\Models\Role;

/**
 * Let the role `owner` open /horizon (ability `horizon.view`) in an app that was seeded before Horizon came to the
 * starter. A new install gets the policy from DatabaseSeeder, which runs after the migrations and so finds no role
 * here yet. Idempotent, goes through the framework Action (a stored event), and only touches `owner`; every other
 * role gets Horizon access on the governance screen.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('policies')) {
            return;
        }

        $scope = StarterGovernanceGate::scopeFor(HorizonAccess::ABILITY);

        foreach (Role::query()->where('name', 'owner')->get() as $role) {
            $granted = Policy::query()
                ->where('role_id', $role->getKey())
                ->where('scope', $scope->value)
                ->get()
                ->contains(fn (Policy $policy): bool => is_array($policy->actions) && array_key_exists(HorizonAccess::ABILITY, $policy->actions));

            if ($granted) {
                continue;
            }

            app(CreatePolicy::class)->handle(
                name: 'Allow '.HorizonAccess::ABILITY,
                description: null,
                scope: $scope,
                resourceType: null,
                resourceId: null,
                roleId: (string) $role->getKey(),
                actions: [HorizonAccess::ABILITY => PolicyEffect::Allow->value],
                effect: PolicyEffect::Allow,
                priority: 100,
                conditions: null,
                orgId: $role->org_id,
                branchId: $role->branch_id,
                actor: 'system:migration',
            );
        }
    }

    public function down(): void
    {
        // Policies are stored events and may have been edited since; leave them.
    }
};
