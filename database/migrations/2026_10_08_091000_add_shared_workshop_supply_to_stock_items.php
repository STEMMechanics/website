<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('stock_items') && ! Schema::hasColumn('stock_items', 'shared_workshop_supply')) {
            Schema::table('stock_items', function (Blueprint $table): void {
                $table->boolean('shared_workshop_supply')->default(false)->after('reorder_point');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('stock_items') && Schema::hasColumn('stock_items', 'shared_workshop_supply')) {
            Schema::table('stock_items', function (Blueprint $table): void {
                $table->dropColumn('shared_workshop_supply');
            });
        }
    }
};
