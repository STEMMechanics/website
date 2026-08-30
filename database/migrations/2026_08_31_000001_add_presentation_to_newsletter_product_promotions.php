<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('newsletter_product_promotions', function (Blueprint $table): void {
            $table->string('subject')->nullable()->after('sections');
            $table->string('hero_header')->nullable()->after('subject');
            $table->text('hero_cta')->nullable()->after('hero_header');
            $table->string('content_order', 20)->nullable()->after('hero_cta');
        });
    }

    public function down(): void
    {
        Schema::table('newsletter_product_promotions', function (Blueprint $table): void {
            $table->dropColumn(['subject', 'hero_header', 'hero_cta', 'content_order']);
        });
    }
};
