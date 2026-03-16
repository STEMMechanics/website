<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->text('private_notes')->nullable()->after('description');
            $table->unsignedInteger('low_stock_threshold')->nullable()->default(5)->after('sort_order');
            $table->timestamp('low_stock_alert_sent_at')->nullable()->after('low_stock_threshold');
        });

        Schema::table('product_variants', function (Blueprint $table): void {
            $table->text('description')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->dropColumn('description');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn([
                'private_notes',
                'low_stock_threshold',
                'low_stock_alert_sent_at',
            ]);
        });
    }
};
