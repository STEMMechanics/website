<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('minecraft_player_stats', function (Blueprint $table): void {
            $table->string('period', 16)->default('all')->after('username');
            $table->unsignedSmallInteger('period_days')->nullable()->after('period');
            $table->index('period');
        });

        DB::table('minecraft_player_stats')->delete();

        Schema::table('minecraft_player_stats', function (Blueprint $table): void {
            $table->dropUnique('minecraft_player_stats_uuid_unique');
            $table->unique(['uuid', 'period']);
        });
    }

    public function down(): void
    {
        DB::table('minecraft_player_stats')->delete();

        Schema::table('minecraft_player_stats', function (Blueprint $table): void {
            $table->dropUnique('minecraft_player_stats_uuid_period_unique');
            $table->dropIndex('minecraft_player_stats_period_index');
            $table->dropColumn(['period', 'period_days']);
            $table->unique('uuid');
        });
    }
};
