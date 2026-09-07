<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workshops', fn (Blueprint $table) => $table->boolean('price_is_automatic')->default(false));
    }
    public function down(): void
    {
        Schema::table('workshops', fn (Blueprint $table) => $table->dropColumn('price_is_automatic'));
    }
};
