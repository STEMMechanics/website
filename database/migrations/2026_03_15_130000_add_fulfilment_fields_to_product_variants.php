<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->boolean('is_preorder')->default(false)->after('weight_grams');
            $table->date('preorder_shipping_estimate')->nullable()->after('is_preorder');
            $table->boolean('allow_backorder')->default(false)->after('preorder_shipping_estimate');
            $table->date('backorder_shipping_estimate')->nullable()->after('allow_backorder');
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->dropColumn([
                'is_preorder',
                'preorder_shipping_estimate',
                'allow_backorder',
                'backorder_shipping_estimate',
            ]);
        });
    }
};
