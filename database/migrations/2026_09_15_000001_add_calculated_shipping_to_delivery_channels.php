<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_shipping_methods', function (Blueprint $table): void {
            $table->decimal('cubic_divisor', 12, 4)->nullable();
            $table->json('weight_tiers')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('store_shipping_methods', function (Blueprint $table): void {
            $table->dropColumn(['cubic_divisor', 'weight_tiers']);
        });
    }
};
