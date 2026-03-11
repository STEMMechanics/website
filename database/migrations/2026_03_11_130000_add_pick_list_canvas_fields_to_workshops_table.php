<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workshops', function (Blueprint $table): void {
            if (! Schema::hasColumn('workshops', 'pick_list_canvas_data')) {
                $table->longText('pick_list_canvas_data')->nullable()->after('pick_list_notes');
            }

            if (! Schema::hasColumn('workshops', 'pick_list_canvas_thumbnail_path')) {
                $table->string('pick_list_canvas_thumbnail_path')->nullable()->after('pick_list_canvas_data');
            }
        });
    }

    public function down(): void
    {
        Schema::table('workshops', function (Blueprint $table): void {
            if (Schema::hasColumn('workshops', 'pick_list_canvas_thumbnail_path')) {
                $table->dropColumn('pick_list_canvas_thumbnail_path');
            }

            if (Schema::hasColumn('workshops', 'pick_list_canvas_data')) {
                $table->dropColumn('pick_list_canvas_data');
            }
        });
    }
};
