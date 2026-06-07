<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Policy;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = (string) config('starter.tenancy.default_tenant_id', 'default');
        $branchId = (string) config('starter.tenancy.default_branch_id', 'main');

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
        // until a policy grants it (D11). The id is a string PK, so generate it
        // only on first create — never reassign it on a re-seed.
        Role::query()
            ->where('name', 'Owner')
            ->update(['name' => 'owner']);

        $owner = Role::query()->firstOrCreate(
            ['name' => 'owner'],
            [
                'id' => (string) Str::ulid(),
                'description' => 'Volledig beheer van governance en gebruikers.',
            ],
        );
        $owner->forceFill([
            'description' => 'Volledig beheer van governance en gebruikers.',
            'scope' => ['all_branches' => true],
            'org_id' => $tenantId,
            'branch_id' => $branchId,
        ])->save();

        foreach (['governance.manage', 'users.manage'] as $action) {
            $policy = Policy::query()->firstOrCreate(
                ['role_id' => $owner->getKey(), 'action' => $action],
                [
                    'id' => (string) Str::ulid(),
                    'effect' => 'allow',
                ],
            );
            $policy->forceFill([
                'name' => 'Allow '.$action,
                'scope' => 'host',
                'actions' => [$action => 'allow'],
                'effect' => 'allow',
                'priority' => 100,
                'conditions' => [],
                'org_id' => $tenantId,
                'branch_id' => $branchId,
            ])->save();
        }

        $admin->roles()->syncWithoutDetaching([$owner->getKey()]);

        DB::table('tenant_user')->updateOrInsert(
            [
                'tenant_id' => $tenantId,
                'user_id' => (string) $admin->getKey(),
            ],
            [
                'role' => 'admin',
                'role_id' => (string) $owner->getKey(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }
}
