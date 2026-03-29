<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_sessions', function (Blueprint $table): void {
            if (! Schema::hasColumn('class_sessions', 'live_broadcast_started_at')) {
                $table->timestamp('live_broadcast_started_at')->nullable()->after('broadcast_sessions_json');
            }

            if (! Schema::hasColumn('class_sessions', 'live_broadcast_ended_at')) {
                $table->timestamp('live_broadcast_ended_at')->nullable()->after('live_broadcast_started_at');
            }

            if (! Schema::hasColumn('class_sessions', 'live_broadcast_started_by_user_id')) {
                $table->foreignUuid('live_broadcast_started_by_user_id')->nullable()->after('live_broadcast_ended_at')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('class_sessions', 'live_broadcast_ended_by_user_id')) {
                $table->foreignUuid('live_broadcast_ended_by_user_id')->nullable()->after('live_broadcast_started_by_user_id')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('class_sessions', function (Blueprint $table): void {
            if (Schema::hasColumn('class_sessions', 'live_broadcast_ended_by_user_id')) {
                $table->dropConstrainedForeignId('live_broadcast_ended_by_user_id');
            }

            if (Schema::hasColumn('class_sessions', 'live_broadcast_started_by_user_id')) {
                $table->dropConstrainedForeignId('live_broadcast_started_by_user_id');
            }

            if (Schema::hasColumn('class_sessions', 'live_broadcast_ended_at')) {
                $table->dropColumn('live_broadcast_ended_at');
            }

            if (Schema::hasColumn('class_sessions', 'live_broadcast_started_at')) {
                $table->dropColumn('live_broadcast_started_at');
            }
        });
    }
};
