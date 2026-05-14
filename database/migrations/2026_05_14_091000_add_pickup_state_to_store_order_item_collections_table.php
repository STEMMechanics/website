<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_order_item_collections', function (Blueprint $table): void {
            $table->string('pickup_state', 24)
                ->default('collected')
                ->after('quantity')
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('store_order_item_collections', function (Blueprint $table): void {
            $table->dropColumn('pickup_state');
        });
    }
};
