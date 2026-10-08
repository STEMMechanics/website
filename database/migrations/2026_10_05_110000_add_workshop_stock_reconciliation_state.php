<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workshops', function (Blueprint $table): void {
            if (! Schema::hasColumn('workshops', 'stock_reconciled_at')) {
                $table->timestamp('stock_reconciled_at')->nullable();
            }

            if (! Schema::hasColumn('workshops', 'stock_reconciled_by')) {
                $table->foreignUuid('stock_reconciled_by')->nullable()->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('workshops', function (Blueprint $table): void {
            if (Schema::hasColumn('workshops', 'stock_reconciled_by')) {
                $table->dropConstrainedForeignId('stock_reconciled_by');
            }

            if (Schema::hasColumn('workshops', 'stock_reconciled_at')) {
                $table->dropColumn('stock_reconciled_at');
            }
        });
    }
};
