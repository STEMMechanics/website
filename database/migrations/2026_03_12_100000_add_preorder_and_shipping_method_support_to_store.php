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
        Schema::create('store_shipping_methods', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('calculator', 30)->default('satchel');
            $table->decimal('flat_rate_amount', 10, 2)->nullable();
            $table->boolean('is_pickup')->default(false);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $timestamp = now();

        DB::table('store_shipping_methods')->insert([
            [
                'code' => 'regular',
                'name' => 'Regular shipping',
                'description' => 'Calculated at checkout based on your order and destination.',
                'calculator' => 'satchel',
                'flat_rate_amount' => null,
                'is_pickup' => false,
                'is_default' => true,
                'is_active' => true,
                'sort_order' => 0,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'code' => 'pickup',
                'name' => 'Pick up',
                'description' => 'Free pickup. We will contact you when your order is available to collect.',
                'calculator' => 'pickup',
                'flat_rate_amount' => 0,
                'is_pickup' => true,
                'is_default' => false,
                'is_active' => true,
                'sort_order' => 1,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
        ]);

        Schema::table('products', function (Blueprint $table): void {
            $table->boolean('is_preorder')->default(false)->after('product_type');
            $table->date('preorder_shipping_estimate')->nullable()->after('is_preorder');
        });

        Schema::table('store_orders', function (Blueprint $table): void {
            $table->boolean('contains_preorder')->default(false)->after('contains_physical');
            $table->boolean('preorder_acknowledged')->default(false)->after('contains_preorder');
            $table->string('shipping_method_code', 40)->nullable()->after('shipping_method');
        });

        Schema::table('store_order_items', function (Blueprint $table): void {
            $table->boolean('is_preorder')->default(false)->after('box_only');
            $table->date('preorder_shipping_estimate')->nullable()->after('is_preorder');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('store_order_items', function (Blueprint $table): void {
            $table->dropColumn([
                'is_preorder',
                'preorder_shipping_estimate',
            ]);
        });

        Schema::table('store_orders', function (Blueprint $table): void {
            $table->dropColumn([
                'contains_preorder',
                'preorder_acknowledged',
                'shipping_method_code',
            ]);
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn([
                'is_preorder',
                'preorder_shipping_estimate',
            ]);
        });

        Schema::dropIfExists('store_shipping_methods');
    }
};
