<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workshops', fn (Blueprint $table) => $table->json('optional_product_ids')->nullable());
    }

    public function down(): void
    {
        Schema::table('workshops', fn (Blueprint $table) => $table->dropColumn('optional_product_ids'));
    }
};
