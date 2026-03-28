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
                $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
                $table->enum('type', ['hand', 'screen', 'camera']);
                $table->enum('status', ['pending', 'approved', 'done', 'rejected'])->default('pending');
                $table->foreignUuid('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();

                $table->index(['class_session_id', 'status', 'created_at']);
                $table->index(['class_session_id', 'user_id', 'status']);
            });

            DB::statement('INSERT INTO class_help_requests_new (id, class_session_id, user_id, type, status, approved_by_user_id, approved_at, resolved_at, created_at, updated_at) SELECT id, class_session_id, user_id, type, status, approved_by_user_id, approved_at, resolved_at, created_at, updated_at FROM class_help_requests');
            Schema::drop('class_help_requests');
            Schema::rename('class_help_requests_new', 'class_help_requests');
            return;
        }

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE class_help_requests MODIFY type ENUM('hand', 'screen', 'camera') NOT NULL");
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE class_help_requests ALTER COLUMN type TYPE VARCHAR(20) USING type::varchar");
            DB::statement("ALTER TABLE class_help_requests DROP CONSTRAINT IF EXISTS class_help_requests_type_check");
            DB::statement("ALTER TABLE class_help_requests ADD CONSTRAINT class_help_requests_type_check CHECK (type IN ('hand', 'screen', 'camera'))");
            return;
        }

        Schema::table('class_help_requests', function (Blueprint $table): void {
            $table->enum('type', ['hand', 'screen', 'camera'])->change();
        });
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            Schema::create('class_help_requests_old', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('class_session_id')->constrained('class_sessions')->cascadeOnDelete();
                $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
                $table->enum('type', ['screen', 'camera']);
                $table->enum('status', ['pending', 'approved', 'done', 'rejected'])->default('pending');
                $table->foreignUuid('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();

                $table->index(['class_session_id', 'status', 'created_at']);
                $table->index(['class_session_id', 'user_id', 'status']);
            });

            DB::statement("INSERT INTO class_help_requests_old (id, class_session_id, user_id, type, status, approved_by_user_id, approved_at, resolved_at, created_at, updated_at) SELECT id, class_session_id, user_id, CASE WHEN type = 'hand' THEN 'screen' ELSE type END, status, approved_by_user_id, approved_at, resolved_at, created_at, updated_at FROM class_help_requests");
            Schema::drop('class_help_requests');
            Schema::rename('class_help_requests_old', 'class_help_requests');
            return;
        }

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE class_help_requests MODIFY type ENUM('screen', 'camera') NOT NULL");
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE class_help_requests DROP CONSTRAINT IF EXISTS class_help_requests_type_check");
            DB::statement("ALTER TABLE class_help_requests ALTER COLUMN type TYPE VARCHAR(20) USING CASE WHEN type = 'hand' THEN 'screen' ELSE type END");
            DB::statement("ALTER TABLE class_help_requests ADD CONSTRAINT class_help_requests_type_check CHECK (type IN ('screen', 'camera'))");
            return;
        }

        Schema::table('class_help_requests', function (Blueprint $table): void {
            $table->enum('type', ['screen', 'camera'])->change();
        });
    }
};
