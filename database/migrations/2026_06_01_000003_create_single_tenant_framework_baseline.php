<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenants')) {
            Schema::create('tenants', function (Blueprint $table): void {
                $table->string('id')->primary();
                $table->timestamps();
                $table->json('data')->nullable();
            });
        }

        if (! Schema::hasTable('tenant_user')) {
            Schema::create('tenant_user', function (Blueprint $table): void {
                $table->string('tenant_id');
                $table->string('user_id');
                $table->string('role')->default('member');
                $table->string('role_id')->nullable();
                $table->timestamps();

                $table->primary(['tenant_id', 'user_id']);
                $table->index('user_id');
                $table->index('role_id');
            });
        }

        Schema::table('roles', function (Blueprint $table): void {
            if (! Schema::hasColumn('roles', 'scope')) {
                $table->json('scope')->nullable()->after('trust_config');
            }

            if (! Schema::hasColumn('roles', 'org_id')) {
                $table->string('org_id', 26)->default('default')->after('scope');
            }

            if (! Schema::hasColumn('roles', 'branch_id')) {
                $table->string('branch_id', 26)->default('main')->after('org_id');
            }

            if (! Schema::hasColumn('roles', 'created_by')) {
                $table->string('created_by', 26)->nullable()->after('branch_id');
            }

            if (! Schema::hasColumn('roles', 'updated_by')) {
                $table->string('updated_by', 26)->nullable()->after('created_by');
            }
        });

        Schema::table('policies', function (Blueprint $table): void {
            if (! Schema::hasColumn('policies', 'name')) {
                $table->string('name')->nullable()->after('id');
            }

            if (! Schema::hasColumn('policies', 'description')) {
                $table->string('description')->nullable()->after('name');
            }

            if (! Schema::hasColumn('policies', 'scope')) {
                $table->string('scope')->default('host')->after('description');
            }

            if (! Schema::hasColumn('policies', 'resource_type')) {
                $table->string('resource_type')->nullable()->after('scope');
            }

            if (! Schema::hasColumn('policies', 'resource_id')) {
                $table->string('resource_id', 26)->nullable()->after('resource_type');
            }

            if (! Schema::hasColumn('policies', 'actions')) {
                $table->json('actions')->nullable()->after('action');
            }

            if (! Schema::hasColumn('policies', 'priority')) {
                $table->integer('priority')->default(0)->after('effect');
            }

            if (! Schema::hasColumn('policies', 'conditions')) {
                $table->json('conditions')->nullable()->after('priority');
            }

            if (! Schema::hasColumn('policies', 'org_id')) {
                $table->string('org_id', 26)->default('default')->after('conditions');
            }

            if (! Schema::hasColumn('policies', 'branch_id')) {
                $table->string('branch_id', 26)->default('main')->after('org_id');
            }

            if (! Schema::hasColumn('policies', 'created_by')) {
                $table->string('created_by', 26)->nullable()->after('branch_id');
            }

            if (! Schema::hasColumn('policies', 'updated_by')) {
                $table->string('updated_by', 26)->nullable()->after('created_by');
            }
        });

        if (! Schema::hasTable('entities')) {
            Schema::create('entities', function (Blueprint $table): void {
                $table->string('id', 26)->primary();
                $table->string('type', 100);
                $table->string('name');
                $table->json('attributes')->nullable();
                $table->json('embedding')->nullable();
                $table->json('field_classifications')->nullable();
                $table->string('status', 50)->default('active');
                $table->string('org_id', 26)->default('default');
                $table->string('branch_id', 26)->default('main');
                $table->string('created_by', 26)->nullable();
                $table->string('updated_by', 26)->nullable();
                $table->string('owner_user_id', 26)->nullable();
                $table->string('visibility')->default('team');
                $table->string('classification')->nullable();
                $table->json('access_metadata')->nullable();
                $table->timestampTz('created_at')->nullable();
                $table->timestampTz('updated_at')->nullable();

                $table->index(['org_id', 'branch_id']);
                $table->index('type');
                $table->index('status');
                $table->index(['org_id', 'type', 'owner_user_id', 'visibility'], 'entities_org_type_owner_vis_idx');
            });
        }

        if (! Schema::hasTable('entity_links')) {
            Schema::create('entity_links', function (Blueprint $table): void {
                $table->string('id', 26)->primary();
                $table->string('source_entity_id', 26);
                $table->string('target_entity_id', 26);
                $table->string('relation_type');
                $table->json('attributes')->nullable();
                $table->string('org_id', 26)->default('default');
                $table->string('branch_id', 26)->default('main');
                $table->string('created_by')->nullable();
                $table->timestamps();

                $table->index('source_entity_id');
                $table->index('target_entity_id');
                $table->unique(['source_entity_id', 'target_entity_id', 'relation_type']);
                $table->index(['org_id', 'branch_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('entity_links');
        Schema::dropIfExists('entities');
        Schema::dropIfExists('tenant_user');
        Schema::dropIfExists('tenants');
    }
};
