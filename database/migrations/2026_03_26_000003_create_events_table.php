<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('events')) {
            return;
        }

        Schema::create('events', function (Blueprint $table): void {
            $jsonColumn = Schema::getConnection()->getDriverName() === 'pgsql' ? 'jsonb' : 'json';

            $table->string('id', 26)->primary();
            $table->string('aggregate_uuid', 26)->nullable();
            $table->string('event_class', 255);
            $table->unsignedInteger('aggregate_version')->default(0);
            $table->unsignedSmallInteger('event_version')->default(1);
            $table->{$jsonColumn}('event_properties');
            $table->{$jsonColumn}('meta_data')->nullable();
            $table->string('aggregate_type', 100)->nullable();
            $table->string('event_type', 100)->nullable();
            $table->string('actor', 26)->nullable();
            $table->string('reason')->nullable();
            $table->string('org_id', 26)->nullable();
            $table->string('branch_id', 26)->nullable();
            $table->timestampTz('timestamp')->useCurrent();
            $table->timestampTz('created_at')->nullable();

            $table->unique(['aggregate_uuid', 'aggregate_version']);
            $table->index('aggregate_type');
            $table->index('event_type');
            $table->index(['org_id', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
