<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pick_list_templates', function (Blueprint $table): void {
            $table->longText('run_sheet_canvas_data')->nullable()->after('run_sheet_drawing_data');
        });
    }

    public function down(): void
    {
        Schema::table('pick_list_templates', function (Blueprint $table): void {
            $table->dropColumn('run_sheet_canvas_data');
        });
    }
};
