<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The change log reads `events` newest first with a cursor on (created_at, id), and filters on the actor. Without
 * these two indexes every page sorts the whole table. Each index is only created when missing, and down() only drops
 * the indexes this migration named.
 */
return new class extends Migration
{
    /** @var array<string, list<string>> */
    private const array INDEXES = [
        'events_created_at_id_index' => ['created_at', 'id'],
        'events_actor_created_at_index' => ['actor', 'created_at'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('events')) {
            return;
        }

        foreach (self::INDEXES as $name => $columns) {
            if (! Schema::hasIndex('events', $name) && ! Schema::hasIndex('events', $columns)) {
                Schema::table('events', fn (Blueprint $table) => $table->index($columns, $name));
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('events')) {
            return;
        }

        foreach (array_keys(self::INDEXES) as $name) {
            if (Schema::hasIndex('events', $name)) {
                Schema::table('events', fn (Blueprint $table) => $table->dropIndex($name));
            }
        }
    }
};
