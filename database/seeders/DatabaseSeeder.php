<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Policy;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
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
        $owner = Role::query()->firstOrCreate(
            ['name' => 'Owner'],
            [
                'id' => (string) Str::ulid(),
                'description' => 'Volledig beheer van governance en gebruikers.',
            ],
        );

        foreach (['governance.manage', 'users.manage'] as $action) {
            Policy::query()->firstOrCreate(
                ['role_id' => $owner->getKey(), 'action' => $action],
                [
                    'id' => (string) Str::ulid(),
                    'effect' => 'allow',
                ],
            );
        }

        $admin->roles()->syncWithoutDetaching([$owner->getKey()]);
    }
}
