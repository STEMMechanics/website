<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('store_shipping_methods', function (Blueprint $table): void {
            $table->unsignedSmallInteger('delivery_estimate_min_days')->nullable()->after('flat_rate_amount');
            $table->unsignedSmallInteger('delivery_estimate_max_days')->nullable()->after('delivery_estimate_min_days');
            $table->decimal('rate_multiplier', 6, 2)->default(1)->after('delivery_estimate_max_days');
            $table->decimal('rate_adjustment_amount', 10, 2)->default(0)->after('rate_multiplier');
        });

        $timestamp = now();

        DB::table('store_shipping_methods')->updateOrInsert(
            ['code' => 'regular'],
            [
                'name' => 'Regular shipping',
                'description' => 'Standard delivery for in-stock items.',
                'calculator' => 'satchel',
                'flat_rate_amount' => null,
                'delivery_estimate_min_days' => 3,
                'delivery_estimate_max_days' => 7,
                'rate_multiplier' => 1,
                'rate_adjustment_amount' => 0,
                'is_pickup' => false,
                'is_default' => true,
                'is_active' => true,
                'sort_order' => 0,
                'updated_at' => $timestamp,
            ],
        );

        DB::table('store_shipping_methods')->updateOrInsert(
            ['code' => 'express'],
            [
                'name' => 'Express shipping',
                'description' => 'Faster delivery once dispatched.',
                'calculator' => 'satchel',
                'flat_rate_amount' => null,
                'delivery_estimate_min_days' => 1,
                'delivery_estimate_max_days' => 3,
                'rate_multiplier' => 1.35,
                'rate_adjustment_amount' => 0,
                'is_pickup' => false,
                'is_default' => false,
                'is_active' => true,
                'sort_order' => 1,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
        );

        DB::table('store_shipping_methods')->updateOrInsert(
            ['code' => 'pickup'],
            [
                'name' => 'Pick up',
                'description' => 'Free pickup. We will contact you when your order is available to collect.',
                'calculator' => 'pickup',
                'flat_rate_amount' => 0,
                'delivery_estimate_min_days' => null,
                'delivery_estimate_max_days' => null,
                'rate_multiplier' => 1,
                'rate_adjustment_amount' => 0,
                'is_pickup' => true,
                'is_default' => false,
                'is_active' => true,
                'sort_order' => 2,
                'updated_at' => $timestamp,
            ],
        );

        Schema::table('products', function (Blueprint $table): void {
            $table->boolean('allow_backorder')->default(false)->after('preorder_shipping_estimate');
            $table->date('backorder_shipping_estimate')->nullable()->after('allow_backorder');
        });

        Schema::table('store_orders', function (Blueprint $table): void {
            $table->boolean('split_shipments')->default(false)->after('contains_preorder');
            $table->boolean('consolidate_shipments')->default(false)->after('split_shipments');
            $table->unsignedTinyInteger('shipment_count')->default(1)->after('consolidate_shipments');
            $table->json('shipping_breakdown_data')->nullable()->after('shipping_package_summary');
        });

        Schema::table('store_order_items', function (Blueprint $table): void {
            $table->unsignedInteger('available_now_quantity')->default(0)->after('quantity');
            $table->unsignedInteger('delayed_quantity')->default(0)->after('available_now_quantity');
            $table->string('delayed_fulfilment_type', 20)->nullable()->after('delayed_quantity');
            $table->date('delayed_shipping_estimate')->nullable()->after('delayed_fulfilment_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('store_order_items', function (Blueprint $table): void {
            $table->dropColumn([
                'available_now_quantity',
                'delayed_quantity',
                'delayed_fulfilment_type',
                'delayed_shipping_estimate',
            ]);
        });

        Schema::table('store_orders', function (Blueprint $table): void {
            $table->dropColumn([
                'split_shipments',
                'consolidate_shipments',
                'shipment_count',
                'shipping_breakdown_data',
            ]);
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn([
                'allow_backorder',
                'backorder_shipping_estimate',
            ]);
        });

        DB::table('store_shipping_methods')->where('code', 'express')->delete();

        Schema::table('store_shipping_methods', function (Blueprint $table): void {
            $table->dropColumn([
                'delivery_estimate_min_days',
                'delivery_estimate_max_days',
                'rate_multiplier',
                'rate_adjustment_amount',
            ]);
        });
    }
};
