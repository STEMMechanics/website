<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_chat_messages', function (Blueprint $table): void {
            if (! Schema::hasColumn('class_chat_messages', 'deleted_at')) {
                $table->timestamp('deleted_at')->nullable()->after('moderation_reason_detail');
            }

            if (! Schema::hasColumn('class_chat_messages', 'deleted_by_user_id')) {
                $table->foreignUuid('deleted_by_user_id')->nullable()->after('deleted_at')->constrained('users')->nullOnDelete();
            }
        });

        if (! Schema::hasTable('class_chat_participant_states')) {
            Schema::create('class_chat_participant_states', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('class_session_id')->constrained()->cascadeOnDelete();
                $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
                $table->foreignUuid('disabled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('disabled_at')->nullable();
                $table->timestamps();

                $table->unique(['class_session_id', 'user_id']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('class_chat_participant_states')) {
            Schema::dropIfExists('class_chat_participant_states');
        }

        Schema::table('class_chat_messages', function (Blueprint $table): void {
            if (Schema::hasColumn('class_chat_messages', 'deleted_by_user_id')) {
                $table->dropConstrainedForeignId('deleted_by_user_id');
            }

            if (Schema::hasColumn('class_chat_messages', 'deleted_at')) {
                $table->dropColumn('deleted_at');
            }
        });
    }
};
