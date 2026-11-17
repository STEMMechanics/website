<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table): void {
            $table->foreignId('pricing_version_id')->nullable()->after('user_id')
                ->constrained('finance_pricing_versions')->restrictOnDelete();
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->foreignId('pricing_version_id')->nullable()->after('quote_id')
                ->constrained('finance_pricing_versions')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('pricing_version_id');
        });

        Schema::table('quotes', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('pricing_version_id');
        });
    }
};
