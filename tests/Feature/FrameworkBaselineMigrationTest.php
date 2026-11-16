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
    $vectorAvailable = DB::table('pg_extension')->where('extname', 'vector')->exists();
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

it('converts populated JSON columns without losing valid embeddings or attributes', function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        Assert::markTestSkipped('Postgres conversion requires Postgres.');
    }

    $migration = require database_path('migrations/2026_11_08_000001_align_framework_columns_on_pgsql.php');
    assert($migration instanceof Migration);
    assert(method_exists($migration, 'up'));
    assert(method_exists($migration, 'down'));
    $migration->down();

    $attributes = ['label' => 'Preserved', 'count' => 7, 'nested' => ['enabled' => true]];
    $valid = array_fill(0, 768, 0.25);
    foreach (['valid' => $valid, 'short' => [1, 2, 3]] as $id => $embedding) {
        DB::table('entities')->insert([
            'id' => 'conversion-'.$id, 'type' => 'test', 'name' => $id,
            'attributes' => json_encode($attributes, JSON_THROW_ON_ERROR),
            'embedding' => json_encode($embedding, JSON_THROW_ON_ERROR),
        ]);
    }

    $migration->up();

    foreach ([
        'entities' => ['attributes', 'field_classifications', 'access_metadata'],
        'entity_links' => ['attributes'],
        'policies' => ['actions', 'conditions'],
        'roles' => ['scope', 'trust_config'],
    ] as $table => $columns) {
        foreach ($columns as $name) {
            $column = collect(Schema::getColumns($table))->firstWhere('name', $name);
            assert(is_array($column));
            expect($column['type_name'])->toBe('jsonb');
        }
    }

    foreach (['valid', 'short'] as $id) {
        $stored = DB::table('entities')->where('id', 'conversion-'.$id)->value('attributes');
        assert(is_string($stored));
        expect(json_decode($stored, true, flags: JSON_THROW_ON_ERROR))->toEqual($attributes);
    }

    $vectorInstalled = DB::table('pg_extension')->where('extname', 'vector')->exists();
    $column = collect(Schema::getColumns('entities'))->firstWhere('name', 'embedding');
    assert(is_array($column));
    expect($column['type'])->toBe($vectorInstalled ? 'vector(768)' : 'json');

    $stored = DB::table('entities')->where('id', 'conversion-valid')->value('embedding');
    assert(is_string($stored));
    expect(json_decode($stored, true, flags: JSON_THROW_ON_ERROR))->toEqual($valid);

    $short = DB::table('entities')->where('id', 'conversion-short')->value('embedding');
    if ($vectorInstalled) {
        expect($short)->toBeNull();
    } else {
        assert(is_string($short));
        expect(json_decode($short, true, flags: JSON_THROW_ON_ERROR))->toBe([1, 2, 3]);
    }
});
