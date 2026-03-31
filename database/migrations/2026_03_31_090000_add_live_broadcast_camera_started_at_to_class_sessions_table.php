<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_sessions', function (Blueprint $table): void {
            if (! Schema::hasColumn('class_sessions', 'live_broadcast_camera_started_at')) {
                $table->timestamp('live_broadcast_camera_started_at')->nullable()->after('live_broadcast_started_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('class_sessions', function (Blueprint $table): void {
            if (Schema::hasColumn('class_sessions', 'live_broadcast_camera_started_at')) {
                $table->dropColumn('live_broadcast_camera_started_at');
            }
        });
    }
};
