<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Access\StarterGovernanceGate;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Mortel\Actions\Policy\CreatePolicy;
use Mortel\Actions\Role\CreateRole;
use Mortel\Enums\PolicyEffect;
use Mortel\Models\Policy;
use Mortel\Models\Role;

final class DatabaseSeeder extends Seeder
{
    private const ACTOR = 'system:seeder';

    public function run(): void
    {
        $tenantId = is_string($t = config('starter.tenancy.default_tenant_id', 'default')) ? $t : 'default';
        $branchId = is_string($b = config('starter.tenancy.default_branch_id', 'main')) ? $b : 'main';

        Tenant::query()->updateOrCreate(
            ['id' => $tenantId],
            [
                'data' => [
                    'name' => config('starter.tenancy.default_tenant_name', 'Default workspace'),
                    'branch_id' => $branchId,
                    'single_tenant' => true,
                ],
            ],
        );

        $admin = User::updateOrCreate(
            ['email' => 'admin@example.test'],
            [
                'name' => 'Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );

        // Owner role with explicit allow policies, so the governance surface is
        // reachable after db:seed. Deny-by-default means nothing is manageable
        // until a policy grants it (D11). Both go through the framework Actions,
        // so the role and its policies are stored events with projections, and
        // re-seeding finds them by name instead of creating them twice.
        $ownerId = $this->ownerRoleId($tenantId, $branchId);

        foreach (StarterGovernanceGate::SCOPES as $ability => $scope) {
            $exists = Policy::query()
                ->where('role_id', $ownerId)
                ->where('scope', $scope->value)
                ->get()
                ->contains(fn (Policy $policy): bool => is_array($policy->actions) && array_key_exists($ability, $policy->actions));

            if ($exists) {
                continue;
            }

            app(CreatePolicy::class)->handle(
                name: 'Allow '.$ability,
                description: null,
                scope: $scope,
                resourceType: null,
                resourceId: null,
                roleId: $ownerId,
                actions: [$ability => PolicyEffect::Allow->value],
                effect: PolicyEffect::Allow,
                priority: 100,
                conditions: null,
                orgId: $tenantId,
                branchId: $branchId,
                actor: self::ACTOR,
            );
        }

        DB::table('tenant_user')->updateOrInsert(
            [
                'tenant_id' => $tenantId,
                'user_id' => $this->key($admin),
            ],
            [
                'role' => 'admin',
                'role_id' => $ownerId,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    private function ownerRoleId(string $tenantId, string $branchId): string
    {
        $existing = Role::query()->where('name', 'owner')->first();

        if ($existing instanceof Role) {
            return $this->key($existing);
        }

        return app(CreateRole::class)->handle(
            name: 'owner',
            description: 'Volledig beheer van governance en gebruikers.',
            trustConfig: [],
            scope: ['all_branches' => true],
            orgId: $tenantId,
            branchId: $branchId,
            actor: self::ACTOR,
        );
    }

    private function key(Model $model): string
    {
        $key = $model->getKey();

        return is_scalar($key) ? (string) $key : '';
    }
}
