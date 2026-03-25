<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('avatar_mode', 16)->nullable()->after('avatar_media_name');
            $table->string('avatar_letters', 3)->nullable()->after('avatar_mode');
            $table->string('avatar_icon_class')->nullable()->after('avatar_letters');
            $table->string('avatar_background_color', 7)->nullable()->after('avatar_icon_class');
            $table->boolean('child_can_select_avatar_media')->default(true)->after('child_parent_notified_on_forum_replies');
            $table->boolean('child_can_use_avatar_camera')->default(true)->after('child_can_select_avatar_media');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'avatar_mode',
                'avatar_letters',
                'avatar_icon_class',
                'avatar_background_color',
                'child_can_select_avatar_media',
                'child_can_use_avatar_camera',
            ]);
        });
    }
};
