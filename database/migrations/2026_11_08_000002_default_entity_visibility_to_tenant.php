<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| The single-tenant baseline of mortelos/starter defaults entities.visibility to `team`, while
| mortelos/framework defaults it to `tenant`. Nothing in the framework knows `team`:
| ContextAccessResolver::visibleQuery() admits `tenant` rows, the owner's rows and explicit
| grants, so a row written without a visibility (the chat entity tool, the API, CreateEntity)
| opened on the record screen but never showed in a list. This migration gives the column the
| framework's default on every driver and turns existing `team` rows into `tenant` rows: no
| code ever wrote `team` on purpose, it only came from the default. This follow-up preserves the original baseline
| for existing installations.
|
| Safe to run again. down() restores the old default but leaves the rows `tenant`: which rows
| were `team` is not recorded, and `team` meant nothing to the access rules anyway.
*/
return new class extends Migration
{
    public function up(): void
    {
        $column = collect(Schema::getColumns('entities'))->firstWhere('name', 'visibility');
        if (trim(explode('::', (string) ($column['default'] ?? ''))[0], "'\"") !== 'tenant') {
            Schema::table('entities', function (Blueprint $table): void {
                $table->string('visibility')->default('tenant')->change();
            });
        }

        DB::table('entities')->where('visibility', 'team')->update(['visibility' => 'tenant']);
    }

    public function down(): void
    {
        Schema::table('entities', function (Blueprint $table): void {
            $table->string('visibility')->default('team')->change();
        });
    }
};
