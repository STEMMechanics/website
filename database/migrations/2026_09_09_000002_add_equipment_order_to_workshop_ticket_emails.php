<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workshop_ticket_emails', function (Blueprint $table) {
            $table->foreignId('equipment_order_id')->nullable()->constrained('store_orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('workshop_ticket_emails', function (Blueprint $table) {
            $table->dropConstrainedForeignId('equipment_order_id');
        });
    }
};
