<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->unsignedInteger('low_stock_threshold')->nullable()->after('sort_order');
            $table->timestamp('low_stock_alert_sent_at')->nullable()->after('low_stock_threshold');
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->dropColumn(['low_stock_threshold', 'low_stock_alert_sent_at']);
        });
    }
};
