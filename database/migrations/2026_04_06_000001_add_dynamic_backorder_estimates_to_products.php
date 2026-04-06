<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('backorder_shipping_estimate_type', 20)->nullable()->after('backorder_shipping_estimate');
            $table->unsignedSmallInteger('backorder_shipping_offset_days')->nullable()->after('backorder_shipping_estimate_type');
        });

        Schema::table('product_variants', function (Blueprint $table): void {
            $table->string('backorder_shipping_estimate_type', 20)->nullable()->after('backorder_shipping_estimate');
            $table->unsignedSmallInteger('backorder_shipping_offset_days')->nullable()->after('backorder_shipping_estimate_type');
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->dropColumn([
                'backorder_shipping_estimate_type',
                'backorder_shipping_offset_days',
            ]);
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn([
                'backorder_shipping_estimate_type',
                'backorder_shipping_offset_days',
            ]);
        });
    }
};
