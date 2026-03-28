<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            Schema::create('class_help_requests_new', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('class_session_id')->constrained('class_sessions')->cascadeOnDelete();
                $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('target_participant_identity')->nullable();
                $table->string('target_username')->nullable();
                $table->string('target_display_name')->nullable();
                $table->foreignUuid('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->enum('type', ['hand', 'screen', 'camera']);
                $table->enum('status', ['pending', 'approved', 'done', 'rejected'])->default('pending');
                $table->foreignUuid('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();

                $table->index(['class_session_id', 'status', 'created_at'], 'class_help_requests_v2_class_session_status_created_at_index');
                $table->index(['class_session_id', 'user_id', 'status'], 'class_help_requests_v2_class_session_user_status_index');
                $table->index(['class_session_id', 'target_participant_identity', 'status'], 'class_help_requests_v2_class_session_target_identity_status_index');
            });

            DB::statement('INSERT INTO class_help_requests_new (id, class_session_id, user_id, target_participant_identity, target_username, target_display_name, requested_by_user_id, type, status, approved_by_user_id, approved_at, resolved_at, created_at, updated_at) SELECT id, class_session_id, user_id, NULL, NULL, NULL, requested_by_user_id, type, status, approved_by_user_id, approved_at, resolved_at, created_at, updated_at FROM class_help_requests');
            Schema::drop('class_help_requests');
            Schema::rename('class_help_requests_new', 'class_help_requests');
            return;
        }

        Schema::table('class_help_requests', function (Blueprint $table): void {
            $table->string('target_participant_identity')->nullable()->after('user_id');
            $table->string('target_username')->nullable()->after('target_participant_identity');
            $table->string('target_display_name')->nullable()->after('target_username');
        });

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE class_help_requests MODIFY user_id CHAR(36) NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE class_help_requests ALTER COLUMN user_id DROP NOT NULL');
        } else {
            Schema::table('class_help_requests', function (Blueprint $table): void {
                $table->foreignUuid('user_id')->nullable()->change();
            });
        }

        Schema::table('class_help_requests', function (Blueprint $table): void {
            $table->index(['class_session_id', 'target_participant_identity', 'status'], 'chreq_sess_target_status_idx');
        });
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            Schema::create('class_help_requests_old', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('class_session_id')->constrained('class_sessions')->cascadeOnDelete();
                $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignUuid('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->enum('type', ['hand', 'screen', 'camera']);
                $table->enum('status', ['pending', 'approved', 'done', 'rejected'])->default('pending');
                $table->foreignUuid('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();

                $table->index(['class_session_id', 'status', 'created_at'], 'class_help_requests_v2_class_session_status_created_at_index');
                $table->index(['class_session_id', 'user_id', 'status'], 'class_help_requests_v2_class_session_user_status_index');
            });

            DB::statement('INSERT INTO class_help_requests_old (id, class_session_id, user_id, requested_by_user_id, type, status, approved_by_user_id, approved_at, resolved_at, created_at, updated_at) SELECT id, class_session_id, user_id, requested_by_user_id, type, status, approved_by_user_id, approved_at, resolved_at, created_at, updated_at FROM class_help_requests');
            Schema::drop('class_help_requests');
            Schema::rename('class_help_requests_old', 'class_help_requests');
            return;
        }

        Schema::table('class_help_requests', function (Blueprint $table): void {
            $table->dropIndex('chreq_sess_target_status_idx');
        });

        Schema::table('class_help_requests', function (Blueprint $table): void {
            $table->dropColumn(['target_participant_identity', 'target_username', 'target_display_name']);
        });

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE class_help_requests MODIFY user_id CHAR(36) NOT NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE class_help_requests ALTER COLUMN user_id SET NOT NULL');
        } else {
            Schema::table('class_help_requests', function (Blueprint $table): void {
                $table->foreignUuid('user_id')->nullable(false)->change();
            });
        }
    }
};
