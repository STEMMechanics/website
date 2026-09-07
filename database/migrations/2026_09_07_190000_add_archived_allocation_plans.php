<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_pricing_versions', fn (Blueprint $table) => $table->boolean('archived')->default(false));
    }

    public function down(): void
    {
        Schema::table('finance_pricing_versions', fn (Blueprint $table) => $table->dropColumn('archived'));
    }
};
