<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Assert;

uses(RefreshDatabase::class);

it('widens every present actor copy column on Postgres', function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        Assert::markTestSkipped('SQLite does not enforce varchar lengths; this assertion requires Postgres.');
    }

    $migration = require database_path('migrations/2026_10_28_100000_widen_event_actor_copy_columns.php');
    assert($migration instanceof Migration);
    /** @var array<string, array<string, bool>> $columns */
    $columns = (new ReflectionClass($migration))->getConstant('COLUMNS');
    $checked = [];

    foreach ($columns as $table => $definitions) {
        foreach ($definitions as $column => $nullable) {
            if (! Schema::hasColumn($table, $column)) {
                continue;
            }

            $definition = DB::table('information_schema.columns')
                ->where('table_schema', 'public')->where('table_name', $table)->where('column_name', $column)->first();

            assert($definition !== null);

            expect($definition->character_maximum_length)->toBe(255)
                ->and($definition->is_nullable)->toBe($nullable ? 'YES' : 'NO');

            $checked[] = $table.'.'.$column;
        }
    }

    expect($checked)->toContain(
        'entities.created_by',
        'entities.updated_by',
        'roles.created_by',
        'roles.updated_by',
        'policies.created_by',
        'policies.updated_by',
    );
});

it('stores agent actors in roles and entities on Postgres', function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        Assert::markTestSkipped('SQLite accepts overlong strings without enforcing varchar lengths.');
    }

    $actor = 'agent:'.str_repeat('x', 26);
    DB::table('roles')->insert(['id' => 'actor-role', 'name' => 'Actor role', 'created_by' => $actor]);
    DB::table('entities')->insert(['id' => 'actor-entity', 'type' => 'test', 'name' => 'Actor entity', 'created_by' => $actor]);

    expect(DB::table('roles')->where('id', 'actor-role')->value('created_by'))->toBe($actor)
        ->and(DB::table('entities')->where('id', 'actor-entity')->value('created_by'))->toBe($actor);
});

it('preserves explicit SQLite indexes through resizing in both directions', function (): void {
    if (DB::getDriverName() !== 'sqlite') {
        Assert::markTestSkipped('sqlite_master and table rebuilds are specific to SQLite.');
    }

    $migration = require database_path('migrations/2026_10_28_100000_widen_event_actor_copy_columns.php');
    assert($migration instanceof Migration);
    assert(method_exists($migration, 'up'));
    assert(method_exists($migration, 'down'));
    $migration->down();
    DB::statement("CREATE UNIQUE INDEX actor_test_partial ON entities (name) WHERE status = 'active'");
    DB::statement("CREATE INDEX actor_test_expression ON entities (json_extract(attributes, '$.code'))");

    /** @var array<string, array<string, bool>> $columns */
    $columns = (new ReflectionClass($migration))->getConstant('COLUMNS');
    $tables = array_keys($columns);
    $indexes = fn () => DB::table('sqlite_master')->where('type', 'index')->whereIn('tbl_name', $tables)
        ->whereNotNull('sql')->orderBy('name')->pluck('sql', 'name')->all();
    $before = $indexes();

    $migration->up();
    expect($indexes())->toBe($before);
    $migration->down();
    expect($indexes())->toBe($before);
    $migration->up();
});
