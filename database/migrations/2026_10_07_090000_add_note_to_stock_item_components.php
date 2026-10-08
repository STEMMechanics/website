<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('stock_item_components') && ! Schema::hasColumn('stock_item_components', 'note')) {
            Schema::table('stock_item_components', function (Blueprint $table): void {
                $table->string('note', 255)->nullable()->after('quantity');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('stock_item_components') && Schema::hasColumn('stock_item_components', 'note')) {
            Schema::table('stock_item_components', function (Blueprint $table): void {
                $table->dropColumn('note');
            });
        }
    }
};
