<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The framework tables behind the inbox screen, in their current shape: the items
 * (Mortel\Models\InboxItem), the learning patterns RejectInboxItem records, and the
 * conversation of an intake item (Mortel\Models\AiConversation over laravel/ai).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inbox_items')) {
            Schema::create('inbox_items', function (Blueprint $table): void {
                $table->string('id', 26)->primary();
                $table->string('user_id', 26)->nullable()->index();
                $table->string('type');
                $table->string('title');
                $table->text('summary');
                $table->string('entity_id', 26)->nullable();
                $table->string('source_type')->nullable();
                $table->string('source_id', 26)->nullable();
                $table->string('risk_level')->default('low');
                $table->string('status')->default('pending');
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->json('metadata')->nullable();
                $table->string('attention_state')->nullable();
                $table->timestampTz('attention_at')->nullable();
                $table->timestampTz('wake_at')->nullable();
                $table->unsignedInteger('attention_revision')->default(0);
                $table->json('wake_condition')->nullable();
                $table->json('attention_config')->nullable();
                $table->string('finding_key', 64)->nullable()->unique();
                $table->timestamps();

                $table->index(['user_id', 'status', 'created_at']);
                $table->index(['source_type', 'source_id'], 'inbox_items_source_index');
                $table->index(['attention_state', 'wake_at']);
            });
        }

        if (! Schema::hasTable('learning_patterns')) {
            Schema::create('learning_patterns', function (Blueprint $table): void {
                $table->ulid('id')->primary();
                $table->string('action_type')->unique();
                $table->text('rejection_sample');
                $table->unsignedInteger('occurrence_count')->default(1);
                $table->timestamp('last_occurred_at');
                $table->boolean('is_active')->default(true);
                $table->softDeletes();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('agent_conversations')) {
            Schema::create('agent_conversations', function (Blueprint $table): void {
                $table->string('id', 36)->primary();
                $table->string('user_id', 26)->nullable();
                $table->string('title');
                $table->string('conversable_type')->nullable();
                $table->string('conversable_id')->nullable();
                $table->timestamps();
                $table->timestamp('archived_at')->nullable();
                $table->timestamp('pinned_at')->nullable();

                $table->index(['user_id', 'updated_at']);
                $table->index(['conversable_type', 'conversable_id'], 'agent_conversations_conversable_index');
                $table->unique(['user_id', 'conversable_type', 'conversable_id'], 'agent_conversations_user_conversable_unique');
                $table->index(['user_id', 'archived_at', 'updated_at'], 'agent_conversations_user_archive_index');
            });
        }

        if (! Schema::hasTable('agent_conversation_messages')) {
            Schema::create('agent_conversation_messages', function (Blueprint $table): void {
                $table->string('id', 36)->primary();
                $table->string('conversation_id', 36)->index();
                $table->string('user_id', 26)->nullable();
                $table->string('agent');
                $table->string('role', 25);
                $table->text('content');
                $table->text('attachments');
                $table->text('tool_calls');
                $table->text('tool_results');
                $table->text('usage');
                $table->text('meta');
                $table->timestamps();

                $table->index(['conversation_id', 'user_id', 'updated_at'], 'conversation_index');
                $table->index(['user_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_conversation_messages');
        Schema::dropIfExists('agent_conversations');
        Schema::dropIfExists('learning_patterns');
        Schema::dropIfExists('inbox_items');
    }
};
