<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workshops', function (Blueprint $table) {
            $table->foreignId('pricing_version_id')->nullable()->constrained('finance_pricing_versions')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('workshops', fn (Blueprint $table) => $table->dropConstrainedForeignId('pricing_version_id'));
    }
};
