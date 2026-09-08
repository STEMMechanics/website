<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_budgets', function (Blueprint $table) {
            $table->timestamp('finalised_at')->nullable();
            $table->string('finalised_by')->nullable();
            $table->string('source_hash', 64)->nullable();
        });
        Schema::table('finance_budget_invoices', function (Blueprint $table) {
            $table->index('invoice_id');
            $table->dropUnique(['invoice_id']);
            $table->unique(['budget_id', 'invoice_id']);
        });
    }

    public function down(): void
    {
        if (DB::table('finance_budget_invoices')->select('invoice_id')->groupBy('invoice_id')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Mixed invoice allocations must be consolidated before rolling back this migration.');
        }
        Schema::table('finance_budget_invoices', function (Blueprint $table) {
            $table->unique('invoice_id');
            $table->dropUnique(['budget_id', 'invoice_id']);
            $table->dropIndex(['invoice_id']);
        });
        Schema::table('finance_budgets', fn (Blueprint $table) => $table->dropColumn(['finalised_at', 'finalised_by', 'source_hash']));
    }
};
