<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_operations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('scope');
            $table->string('grant')->nullable();
            $table->string('user_id');
            $table->string('action');
            $table->string('idempotency_key');
            $table->char('input_hash', 64);
            $table->json('item_ref')->nullable();
            $table->string('status');
            $table->json('receipt')->nullable();
            $table->string('correlation_id');
            $table->timestamps();
            $table->unique(['scope', 'action', 'idempotency_key'], 'api_operations_idempotency_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_operations');
    }
};
