<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_shipping_methods', function (Blueprint $table): void {
            $table->string('shipment_label', 80)->nullable()->after('description');
            $table->string('immediate_status_label', 80)->nullable()->after('shipment_label');
            $table->string('delayed_status_label', 80)->nullable()->after('immediate_status_label');
        });

        DB::table('store_shipping_methods')
            ->where('is_pickup', true)
            ->update([
                'shipment_label' => 'Collection',
                'immediate_status_label' => 'Available now',
                'delayed_status_label' => 'Available later',
            ]);

        DB::table('store_shipping_methods')
            ->where('is_pickup', false)
            ->update([
                'shipment_label' => 'Shipment',
                'immediate_status_label' => 'Ships now',
                'delayed_status_label' => 'Ships later',
            ]);
    }

    public function down(): void
    {
        Schema::table('store_shipping_methods', function (Blueprint $table): void {
            $table->dropColumn([
                'shipment_label',
                'immediate_status_label',
                'delayed_status_label',
            ]);
        });
    }
};
