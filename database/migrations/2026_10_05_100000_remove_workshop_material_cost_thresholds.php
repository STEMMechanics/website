<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('workshops', 'stock_cost_threshold_ex_tax')) {
            Schema::table('workshops', function (Blueprint $table): void {
                $table->dropColumn('stock_cost_threshold_ex_tax');
            });
        }

        if (Schema::hasColumn('pick_list_templates', 'stock_cost_threshold_ex_tax')) {
            Schema::table('pick_list_templates', function (Blueprint $table): void {
                $table->dropColumn('stock_cost_threshold_ex_tax');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('workshops', 'stock_cost_threshold_ex_tax')) {
            Schema::table('workshops', function (Blueprint $table): void {
                $table->decimal('stock_cost_threshold_ex_tax', 12, 2)->nullable();
            });
        }

        if (! Schema::hasColumn('pick_list_templates', 'stock_cost_threshold_ex_tax')) {
            Schema::table('pick_list_templates', function (Blueprint $table): void {
                $table->decimal('stock_cost_threshold_ex_tax', 12, 2)->nullable();
            });
        }
    }
};
