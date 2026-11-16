<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Assert;

uses(RefreshDatabase::class);

it('defaults an entity without visibility to tenant', function (): void {
    DB::table('entities')->insert(['id' => 'baseline-entity', 'type' => 'test', 'name' => 'Baseline entity']);

    expect(DB::table('entities')->where('id', 'baseline-entity')->value('visibility'))->toBe('tenant');
});

it('aligns the framework schema on Postgres', function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        Assert::markTestSkipped('Postgres column types require Postgres.');
    }

    $columns = [
        'entities' => ['attributes', 'field_classifications', 'access_metadata'],
        'entity_links' => ['attributes'],
        'policies' => ['actions', 'conditions'],
        'roles' => ['scope', 'trust_config'],
    ];

    foreach ($columns as $table => $names) {
        foreach ($names as $name) {
            $column = collect(Schema::getColumns($table))->firstWhere('name', $name);
            assert(is_array($column));
            expect($column['type_name'])->toBe('jsonb');
        }
    }

    $visibility = collect(Schema::getColumns('entities'))->firstWhere('name', 'visibility');
    assert(is_array($visibility));
    expect($visibility['default'])->toBe("'tenant'::character varying");

    $embedding = collect(Schema::getColumns('entities'))->firstWhere('name', 'embedding');
    assert(is_array($embedding));
    $vectorAvailable = DB::table('pg_available_extensions')->where('name', 'vector')->exists();
    expect($embedding['type'])->toBe($vectorAvailable ? 'vector(768)' : 'json');

    if ($vectorAvailable) {
        expect(DB::table('pg_indexes')->where('schemaname', 'public')
            ->where('indexname', 'entities_embedding_hnsw_idx')->exists())->toBeTrue();
    }
});

it('can reapply the corrections and converts old team visibility', function (): void {
    DB::table('entities')->insert(['id' => 'legacy-entity', 'type' => 'test', 'name' => 'Legacy entity', 'visibility' => 'team']);

    foreach ([
        '2026_11_08_000001_align_framework_columns_on_pgsql.php',
        '2026_11_08_000002_default_entity_visibility_to_tenant.php',
    ] as $file) {
        $migration = require database_path('migrations/'.$file);
        assert($migration instanceof Migration);
        assert(method_exists($migration, 'up'));
        $migration->up();
        $migration->up();
    }

    expect(DB::table('entities')->where('id', 'legacy-entity')->value('visibility'))->toBe('tenant');
});
