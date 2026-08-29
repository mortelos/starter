<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Role assignment pivot (D11): which user holds which role.
 *
 * `user_id` is the bigint users PK; `role_id` is the string roles PK. This is a
 * legacy governance-role assignment. Tenant membership and its active role are
 * authoritative in the tenant_user pivot created by the baseline migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_user', function (Blueprint $table): void {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role_id');
            $table->timestamps();

            $table->primary(['user_id', 'role_id']);
            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_user');
    }
};
