<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->timestamp('restocked_at')->nullable()->after('low_stock_alert_sent_at')->index();
        });

        Schema::table('product_variants', function (Blueprint $table): void {
            $table->timestamp('restocked_at')->nullable()->after('low_stock_alert_sent_at')->index();
        });

        Schema::create('newsletter_product_promotions', function (Blueprint $table): void {
            $table->id();
            $table->string('title')->nullable();
            $table->text('intro')->nullable();
            $table->json('product_ids')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_product_promotions');

        Schema::table('product_variants', function (Blueprint $table): void {
            $table->dropColumn('restocked_at');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('restocked_at');
        });
    }
};
