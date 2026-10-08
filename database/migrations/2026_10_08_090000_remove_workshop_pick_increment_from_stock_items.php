<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('stock_items') && Schema::hasColumn('stock_items', 'workshop_pick_increment')) {
            Schema::table('stock_items', function (Blueprint $table): void {
                $table->dropColumn('workshop_pick_increment');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('stock_items') && ! Schema::hasColumn('stock_items', 'workshop_pick_increment')) {
            Schema::table('stock_items', function (Blueprint $table): void {
                $table->decimal('workshop_pick_increment', 12, 3)->default(1)->after('reorder_point');
            });
        }
    }
};
