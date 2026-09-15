<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_shipping_methods', function (Blueprint $table): void {
            $table->decimal('calculated_packaging_cost', 8, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('store_shipping_methods', function (Blueprint $table): void {
            $table->dropColumn('calculated_packaging_cost');
        });
    }
};
