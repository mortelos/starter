<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
| On Postgres the single-tenant baseline of mortelos/starter made these framework columns
| `json`, while mortelos/framework creates them as `jsonb` and queries them with jsonb
| operators (the disclosure filter of the list engine compares field_classifications
| with '{}'). The embedding becomes the framework's `vector(768)` with its HNSW index, so
| semantic search works with pgvector. SQLite has one JSON type and no vectors: nothing
| changes there. This follow-up preserves the original baseline for existing installations.
|
| Safe to run again: a column that already has the target type is left alone. An embedding
| that is not a list of exactly 768 numbers (another model, a broken value) cannot become
| vector(768); it is set to null first, and the embedding job writes it again.
*/
return new class extends Migration
{
    private const int DIMENSIONS = 768;

    /** @var array<string, list<string>> */
    private const array JSONB = [
        'entities' => ['attributes', 'field_classifications', 'access_metadata'],
        'entity_links' => ['attributes'],
        'policies' => ['actions', 'conditions'],
        'roles' => ['scope', 'trust_config'],
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $this->alter('json', 'jsonb');

        if (! DB::table('pg_available_extensions')->where('name', 'vector')->exists()) {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
        $dimensions = self::DIMENSIONS;

        if ($this->type('entities', 'embedding') === 'json') {
            DB::statement("UPDATE entities SET embedding = NULL WHERE embedding IS NOT NULL AND (json_typeof(embedding) <> 'array' OR json_array_length(embedding) <> {$dimensions})");
            DB::statement("ALTER TABLE entities ALTER COLUMN embedding TYPE vector({$dimensions}) USING embedding::text::vector({$dimensions})");
        } elseif ($this->type('entities', 'embedding') === 'vector' && $this->vectorDimensions() !== $dimensions) {
            DB::statement("UPDATE entities SET embedding = NULL WHERE embedding IS NOT NULL AND vector_dims(embedding) <> {$dimensions}");
            DB::statement("ALTER TABLE entities ALTER COLUMN embedding TYPE vector({$dimensions})");
        }

        DB::statement('CREATE INDEX IF NOT EXISTS entities_embedding_hnsw_idx ON entities USING hnsw (embedding vector_cosine_ops)');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS entities_embedding_hnsw_idx');
        if ($this->type('entities', 'embedding') === 'vector') {
            DB::statement('ALTER TABLE entities ALTER COLUMN embedding TYPE json USING embedding::text::json');
        }
        $this->alter('jsonb', 'json');
    }

    private function alter(string $from, string $to): void
    {
        foreach (self::JSONB as $table => $columns) {
            $changes = [];
            foreach ($columns as $column) {
                if ($this->type($table, $column) === $from) {
                    $changes[] = "ALTER COLUMN {$column} TYPE {$to} USING {$column}::{$to}";
                }
            }
            if ($changes !== []) {
                DB::statement("ALTER TABLE {$table} ".implode(', ', $changes));
            }
        }
    }

    /** The column's type name (`json`, `jsonb`, `vector`), or null when it does not exist. */
    private function type(string $table, string $column): ?string
    {
        $type = DB::table('information_schema.columns')
            ->where('table_schema', DB::raw('current_schema()'))
            ->where('table_name', $table)
            ->where('column_name', $column)
            ->value('udt_name');

        return is_string($type) ? $type : null;
    }

    /** The declared dimensions of entities.embedding; null for a plain `vector` without them. */
    private function vectorDimensions(): ?int
    {
        $modifier = DB::selectOne("SELECT atttypmod FROM pg_attribute WHERE attrelid = 'entities'::regclass AND attname = 'embedding'");
        $value = is_object($modifier) && isset($modifier->atttypmod) && is_numeric($modifier->atttypmod) ? (int) $modifier->atttypmod : -1;

        return $value > 0 ? $value : null;
    }
};
