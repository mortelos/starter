<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('context_grants', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('grantee_type');
            $table->string('grantee_id', 26)->nullable();
            $table->string('resource_type');
            $table->string('resource_id', 26)->nullable();
            $table->jsonb('actions');
            $table->string('granted_by', 26);
            $table->ulid('org_id');
            $table->ulid('branch_id');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['grantee_type', 'grantee_id']);
            $table->index(['resource_type', 'resource_id']);
            $table->index(['org_id', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('context_grants');
    }
};
