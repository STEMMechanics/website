<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('newsletter_product_promotions', fn (Blueprint $table) => $table->json('personal_note')->nullable());
    }

    public function down(): void
    {
        Schema::table('newsletter_product_promotions', fn (Blueprint $table) => $table->dropColumn('personal_note'));
    }
};
