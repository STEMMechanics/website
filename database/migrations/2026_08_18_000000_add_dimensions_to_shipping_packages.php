<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_shipping_method_packages', function (Blueprint $table): void {
            $table->unsignedInteger('internal_length_mm')->nullable()->after('capacity');
            $table->unsignedInteger('internal_width_mm')->nullable()->after('internal_length_mm');
            $table->unsignedInteger('internal_height_mm')->nullable()->after('internal_width_mm');
            $table->unsignedInteger('max_weight_grams')->nullable()->after('internal_height_mm');
        });

    }

    public function down(): void
    {
        Schema::table('store_shipping_method_packages', function (Blueprint $table): void {
            $table->dropColumn([
                'internal_length_mm',
                'internal_width_mm',
                'internal_height_mm',
                'max_weight_grams',
            ]);
        });
    }
};
