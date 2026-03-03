<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forum_categories', function (Blueprint $table): void {
            $table->string('icon_class', 120)->nullable()->after('description');
            $table->string('color_hex', 7)->nullable()->after('icon_class');
        });
    }

    public function down(): void
    {
        Schema::table('forum_categories', function (Blueprint $table): void {
            $table->dropColumn(['icon_class', 'color_hex']);
        });
    }
};
