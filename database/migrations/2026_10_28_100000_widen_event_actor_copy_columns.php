<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The projectors copy the actor of an event into these columns, and
 * Mortel\Support\Actor writes "agent:<run-id>" and "bridge:<name>" (32+
 * characters). Postgres rejects that in a varchar(26). Same fix as
 * `events.actor` (2026_10_14_000001): none of these columns has an index or
 * foreign key, so it is a plain type change that keeps nullability.
 *
 * SQLite rebuilds the table for a type change and recreates the indexes from
 * introspection, which loses a WHERE clause (the partial unique index on
 * entities, 2026_04_27_120001) and cannot express an index on an expression
 * (hosts add those, e.g. json_extract). So on SQLite the explicitly created
 * indexes of the table are dropped before the rebuild and recreated afterwards
 * from their stored SQL. Postgres and MySQL alter the column in place.
 */
return new class extends Migration
{
    /** @var array<string, array<string, bool>> table => column => nullable */
    private const COLUMNS = [
        'entities' => ['created_by' => true, 'updated_by' => true],
        'roles' => ['created_by' => true, 'updated_by' => true],
        'policies' => ['created_by' => true, 'updated_by' => true],
        'channels' => ['created_by' => true, 'updated_by' => true],
        'workflows' => ['created_by' => true, 'updated_by' => true],
        'documents' => ['created_by' => false],
        'document_versions' => ['author_id' => false],
    ];

    public function up(): void
    {
        $this->resize(255);
    }

    public function down(): void
    {
        $this->resize(26);
    }

    private function resize(int $length): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $present = array_filter($columns, fn (bool $nullable, string $column): bool => Schema::hasColumn($table, $column), ARRAY_FILTER_USE_BOTH);

            if ($present === []) {
                continue;
            }

            $indexes = $this->sqliteIndexes($table);

            foreach ($indexes as $name => $sql) {
                DB::statement('DROP INDEX IF EXISTS "'.str_replace('"', '""', $name).'"');
            }

            Schema::table($table, function (Blueprint $blueprint) use ($present, $length): void {
                foreach ($present as $column => $nullable) {
                    $definition = $blueprint->string($column, $length);

                    if ($nullable) {
                        $definition->nullable();
                    }

                    $definition->change();
                }
            });

            foreach ($indexes as $sql) {
                DB::statement($sql);
            }
        }
    }

    /**
     * @return array<string, string> index name => the SQL it was created with
     */
    private function sqliteIndexes(string $table): array
    {
        if (DB::getDriverName() !== 'sqlite') {
            return [];
        }

        $indexes = [];

        $stored = DB::table('sqlite_master')
            ->where('type', 'index')
            ->where('tbl_name', $table)
            ->whereNotNull('sql')
            ->pluck('sql', 'name');

        foreach ($stored as $name => $sql) {
            if (is_string($sql)) {
                $indexes[(string) $name] = $sql;
            }
        }

        return $indexes;
    }
};
