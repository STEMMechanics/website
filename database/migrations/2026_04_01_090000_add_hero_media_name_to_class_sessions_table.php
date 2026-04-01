<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_sessions', function (Blueprint $table): void {
            if (! Schema::hasColumn('class_sessions', 'hero_media_name')) {
                $table->string('hero_media_name')->nullable()->after('room_name');
                $table->foreign('hero_media_name')->references('name')->on('media')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('class_sessions', function (Blueprint $table): void {
            if (Schema::hasColumn('class_sessions', 'hero_media_name')) {
                $table->dropForeign(['hero_media_name']);
                $table->dropColumn('hero_media_name');
            }
        });
    }
};
