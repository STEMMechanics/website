<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_purchases', function (Blueprint $table): void {
            $table->boolean('individual_units')->default(false);
            $table->unsignedInteger('units_per_purchase')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('product_purchases', function (Blueprint $table): void {
            $table->dropColumn(['individual_units', 'units_per_purchase']);
        });
    }
};
