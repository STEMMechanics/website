<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_payment_allocations', function (Blueprint $table) {
            $table->foreign('tax_adjustment_id')
                ->references('id')
                ->on('tax_adjustments')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoice_payment_allocations', function (Blueprint $table) {
            $table->dropForeign(['tax_adjustment_id']);
        });
    }
};

