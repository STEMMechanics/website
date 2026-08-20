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
            $table->boolean('suppresses_request_quote')->default(true)->after('is_pickup');
        });

        DB::table('store_shipping_methods')->where('is_pickup', true)->update([
            'suppresses_request_quote' => false,
        ]);
    }

    public function down(): void
    {
        Schema::table('store_shipping_methods', function (Blueprint $table): void {
            $table->dropColumn('suppresses_request_quote');
        });
    }
};
