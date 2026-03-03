<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedSmallInteger('avatar_zoom')->default(100)->after('avatar_media_name');
            $table->smallInteger('avatar_offset_x')->default(0)->after('avatar_zoom');
            $table->smallInteger('avatar_offset_y')->default(0)->after('avatar_offset_x');
        });

        DB::table('users')->update([
            'avatar_zoom' => 100,
            'avatar_offset_x' => 0,
            'avatar_offset_y' => 0,
        ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['avatar_zoom', 'avatar_offset_x', 'avatar_offset_y']);
        });
    }
};
