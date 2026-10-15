<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_shipping_methods', function (Blueprint $table): void {
            $table->string('pricing_type', 30)->nullable();
            $table->json('cart_value_tiers')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('store_shipping_methods', function (Blueprint $table): void {
            $table->dropColumn(['pricing_type', 'cart_value_tiers']);
        });
    }
};
