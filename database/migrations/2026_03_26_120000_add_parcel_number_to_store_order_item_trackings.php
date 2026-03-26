<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('store_order_item_trackings', 'parcel_number')) {
            Schema::table('store_order_item_trackings', function (Blueprint $table): void {
                $table->unsignedInteger('parcel_number')->nullable()->after('tracking_url');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('store_order_item_trackings', 'parcel_number')) {
            Schema::table('store_order_item_trackings', function (Blueprint $table): void {
                $table->dropColumn('parcel_number');
            });
        }
    }
};
