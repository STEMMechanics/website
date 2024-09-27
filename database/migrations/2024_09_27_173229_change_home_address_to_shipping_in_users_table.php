<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('home_address', 'shipping_address');
            $table->renameColumn('home_address2', 'shipping_address2');
            $table->renameColumn('home_city', 'shipping_city');
            $table->renameColumn('home_state', 'shipping_state');
            $table->renameColumn('home_postcode', 'shipping_postcode');
            $table->renameColumn('home_country', 'shipping_country');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('shipping_address', 'home_address');
            $table->renameColumn('shipping_address2', 'home_address2');
            $table->renameColumn('shipping_city', 'home_city');
            $table->renameColumn('shipping_state', 'home_state');
            $table->renameColumn('shipping_postcode', 'home_postcode');
            $table->renameColumn('shipping_country', 'home_country');
        });
    }
};
