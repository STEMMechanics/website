<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('newsletter_product_promotions', function (Blueprint $table) {
            $table->string('hero_image_name')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('newsletter_product_promotions', function (Blueprint $table) {
            $table->dropColumn('hero_image_name');
        });
    }
};
