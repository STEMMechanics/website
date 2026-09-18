<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('newsletter_product_promotions', function (Blueprint $table): void {
            $table->json('excluded_workshop_ids')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('newsletter_product_promotions', function (Blueprint $table): void {
            $table->dropColumn('excluded_workshop_ids');
        });
    }
};
