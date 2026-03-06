<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('minecraft_player_stats', function (Blueprint $table): void {
            $table->string('platform', 20)->default('java')->after('uuid');
        });

        Schema::table('minecraft_player_stats', function (Blueprint $table): void {
            $table->dropUnique('minecraft_player_stats_uuid_period_unique');
            $table->unique(['uuid', 'platform', 'period']);
            $table->index('platform');
        });
    }

    public function down(): void
    {
        Schema::table('minecraft_player_stats', function (Blueprint $table): void {
            $table->dropUnique('minecraft_player_stats_uuid_platform_period_unique');
            $table->dropIndex('minecraft_player_stats_platform_index');
            $table->dropColumn('platform');
            $table->unique(['uuid', 'period']);
        });
    }
};
