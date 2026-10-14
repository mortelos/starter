<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mortel\Support\Actor writes `agent:<run-id>`, `system:<process>` and
 * `bridge:<name>` into `events.actor`; 26 characters only fit a user ULID.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', fn (Blueprint $table) => $table->string('actor', 255)->nullable()->change());
    }

    public function down(): void
    {
        Schema::table('events', fn (Blueprint $table) => $table->string('actor', 26)->nullable()->change());
    }
};
