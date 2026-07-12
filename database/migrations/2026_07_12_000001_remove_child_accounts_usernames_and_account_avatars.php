<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            foreach ([
                'parent_user_id',
                'avatar_media_name',
                'avatar_mode',
                'avatar_letters',
                'avatar_icon_class',
                'avatar_background_color',
                'avatar_zoom',
                'avatar_offset_x',
                'avatar_offset_y',
                'username',
                'child_can_select_avatar_media',
                'child_can_use_avatar_camera',
            ] as $column) {
                if (! Schema::hasColumn('users', $column)) {
                    continue;
                }

                if ($column === 'parent_user_id') {
                    $table->dropConstrainedForeignId($column);
                } elseif ($column === 'avatar_media_name') {
                    $table->dropForeign([$column]);
                    $table->dropColumn($column);
                } elseif ($column === 'username') {
                    $table->dropUnique([$column]);
                    $table->dropColumn($column);
                } else {
                    $table->dropColumn($column);
                }
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'parent_user_id')) {
                $table->uuid('parent_user_id')->nullable()->after('id');
            }

            if (! Schema::hasColumn('users', 'username')) {
                $table->string('username', 32)->nullable()->after('email');
            }

            if (! Schema::hasColumn('users', 'avatar_media_name')) {
                $table->string('avatar_media_name')->nullable()->after('email_verified_at');
            }

            if (! Schema::hasColumn('users', 'avatar_mode')) {
                $table->string('avatar_mode', 16)->nullable()->after('avatar_media_name');
            }

            if (! Schema::hasColumn('users', 'avatar_letters')) {
                $table->string('avatar_letters', 3)->nullable()->after('avatar_mode');
            }

            if (! Schema::hasColumn('users', 'avatar_icon_class')) {
                $table->string('avatar_icon_class')->nullable()->after('avatar_letters');
            }

            if (! Schema::hasColumn('users', 'avatar_background_color')) {
                $table->string('avatar_background_color', 7)->nullable()->after('avatar_icon_class');
            }

            if (! Schema::hasColumn('users', 'avatar_zoom')) {
                $table->unsignedSmallInteger('avatar_zoom')->default(100)->after('avatar_media_name');
            }

            if (! Schema::hasColumn('users', 'avatar_offset_x')) {
                $table->smallInteger('avatar_offset_x')->default(0)->after('avatar_zoom');
            }

            if (! Schema::hasColumn('users', 'avatar_offset_y')) {
                $table->smallInteger('avatar_offset_y')->default(0)->after('avatar_offset_x');
            }

            if (! Schema::hasColumn('users', 'child_can_select_avatar_media')) {
                $table->boolean('child_can_select_avatar_media')->default(true);
            }

            if (! Schema::hasColumn('users', 'child_can_use_avatar_camera')) {
                $table->boolean('child_can_use_avatar_camera')->default(true);
            }
        });
    }
};
