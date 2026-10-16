<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reading the events of one day filters `events` on created_at, and hosts look
 * events up by event_class. Neither column had an index, so both read the whole
 * table including every payload (CREST R-427). spatie/laravel-event-sourcing
 * indexes event_class in its own stored_events table too. A host may already
 * have added an index on one of these columns, so each one is only created when
 * missing, and down() only drops the indexes this migration named.
 */
return new class extends Migration
{
    private const array COLUMNS = ['created_at', 'event_class'];

    public function up(): void
    {
        if (! Schema::hasTable('events')) {
            return;
        }

        foreach (self::COLUMNS as $column) {
            if (! Schema::hasIndex('events', [$column])) {
                Schema::table('events', fn (Blueprint $table) => $table->index($column, "events_{$column}_index"));
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('events')) {
            return;
        }

        foreach (self::COLUMNS as $column) {
            if (Schema::hasIndex('events', "events_{$column}_index")) {
                Schema::table('events', fn (Blueprint $table) => $table->dropIndex("events_{$column}_index"));
            }
        }
    }
};
