<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('store_shipping_methods') && Schema::hasColumn('store_shipping_methods', 'is_default')) {
            Schema::table('store_shipping_methods', function (Blueprint $table): void {
                $table->dropColumn('is_default');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('store_shipping_methods') && ! Schema::hasColumn('store_shipping_methods', 'is_default')) {
            Schema::table('store_shipping_methods', function (Blueprint $table): void {
                $table->boolean('is_default')->default(false)->after('is_pickup');
            });
        }
    }
};
