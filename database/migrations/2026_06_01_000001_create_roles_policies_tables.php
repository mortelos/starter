<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Roles + policies as owner-editable DB data (D11).
 *
 * Single-tenant baseline (database-driver placement variant): one default DB, no
 * tenant_id columns. The add-tenancy skill makes these tenant-scoped when
 * multi-tenancy is added.
 *
 * Deny-by-default is enforced by the gate (absence of an `allow` row = deny),
 * not by a column default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('description')->nullable();
            $table->json('trust_config')->nullable();
            $table->timestamps();
        });

        Schema::create('policies', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('role_id');
            $table->string('action');
            $table->string('effect')->default('deny'); // 'allow' | 'deny'
            $table->timestamps();

            $table->index(['role_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('policies');
        Schema::dropIfExists('roles');
    }
};
