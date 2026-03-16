<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_orders', function (Blueprint $table): void {
            $table->text('public_notes')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('store_orders', function (Blueprint $table): void {
            $table->dropColumn('public_notes');
        });
    }
};
