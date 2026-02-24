<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            if (! Schema::hasColumn('invoices', 'quote_id')) {
                $table->foreignId('quote_id')->nullable()->after('invoice_number')->constrained('quotes')->nullOnDelete();
                $table->unique('quote_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            if (Schema::hasColumn('invoices', 'quote_id')) {
                $table->dropUnique(['quote_id']);
                $table->dropConstrainedForeignId('quote_id');
            }
        });
    }
};

