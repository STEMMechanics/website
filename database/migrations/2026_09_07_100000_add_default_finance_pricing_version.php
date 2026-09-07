<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_settings', function (Blueprint $table) {
            $table->foreignId('default_pricing_version_id')->nullable()->constrained('finance_pricing_versions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('finance_settings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_pricing_version_id');
        });
    }
};
