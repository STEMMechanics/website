<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('stock_items') || Schema::hasColumn('stock_items', 'image_media_name')) {
            return;
        }

        Schema::table('stock_items', function (Blueprint $table): void {
            $table->string('image_media_name')->nullable()->after('sku');
            $table->foreign('image_media_name')->references('name')->on('media')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('stock_items') || ! Schema::hasColumn('stock_items', 'image_media_name')) {
            return;
        }

        Schema::table('stock_items', function (Blueprint $table): void {
            $table->dropForeign(['image_media_name']);
            $table->dropColumn('image_media_name');
        });
    }
};
