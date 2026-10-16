<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->boolean('automatic_allocation')->default(false);
            $table->string('purchase_cost_centre', 20)->default('auto');
            $table->json('allocation_remainder')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn(['automatic_allocation', 'purchase_cost_centre', 'allocation_remainder']);
        });
    }
};
